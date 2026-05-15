/*
 * AfterParseAction PHP extension
 * Copyright (c) 2026 Grzegorz Drozd
 * Licensed under the AGPL-3.0 license. See LICENSE file.
 */

#include "php.h"
#include "php_apa.h"
#include "apa_handler.h"
#include "zend_attributes.h"
#include "zend_exceptions.h"
#include "zend_observer.h"

/* --- Constants --- */

#define APA_ATTR_NAME     "afterparseaction"
#define APA_ATTR_NAME_LEN (sizeof(APA_ATTR_NAME) - 1)

/* --- Static state --- */

static void (*original_execute_ex)(zend_execute_data *execute_data);

/* --- Pending action lifecycle --- */

static void pending_action_dtor(void *ptr)
{
    apa_pending_action *action = (apa_pending_action *)ptr;
    zval_ptr_dtor(&action->callable);
    for (uint32_t i = 0; i < action->extra_argc; i++) {
        zval_ptr_dtor(&action->extra_args[i]);
        if (action->extra_names[i]) {
            zend_string_release(action->extra_names[i]);
        }
    }
    if (action->extra_args) {
        efree(action->extra_args);
    }
    if (action->extra_names) {
        efree(action->extra_names);
    }
    if (action->target_class) {
        zend_string_release(action->target_class);
    }
    if (action->target_method) {
        zend_string_release(action->target_method);
    }
}

/**
 * Copy attribute data into a pending action.
 * All values are resolved and copied — no dangling pointers to zend_attribute.
 */
static void apa_queue_action(zend_attribute *attr, zend_string *class_name,
                             zend_string *method_name)
{
    apa_pending_action action;
    memset(&action, 0, sizeof(action));

    /* Copy callable (first attribute arg) */
    ZVAL_UNDEF(&action.callable);
    if (zend_get_attribute_value(&action.callable, attr, 0, NULL) == FAILURE) {
        php_error_docref(NULL, E_WARNING,
            "AfterParseAction: failed to resolve callable argument");
        return;
    }

    /* Copy extra args */
    action.extra_argc = (attr->argc > 1) ? (attr->argc - 1) : 0;
    if (action.extra_argc > 0) {
        action.extra_args = emalloc(sizeof(zval) * action.extra_argc);
        action.extra_names = emalloc(sizeof(zend_string *) * action.extra_argc);
        for (uint32_t i = 0; i < action.extra_argc; i++) {
            ZVAL_UNDEF(&action.extra_args[i]);
            if (zend_get_attribute_value(&action.extra_args[i], attr, i + 1, NULL) == FAILURE) {
                ZVAL_NULL(&action.extra_args[i]);
            }
            action.extra_names[i] = attr->args[i + 1].name
                ? zend_string_copy(attr->args[i + 1].name)
                : NULL;
        }
    } else {
        action.extra_args = NULL;
        action.extra_names = NULL;
    }

    action.target_class = class_name ? zend_string_copy(class_name) : NULL;
    action.target_method = method_name ? zend_string_copy(method_name) : NULL;

    zend_llist_add_element(&APA_G(pending_actions), &action);
}

/* --- Execute a single queued action --- */

static void apa_execute_action(apa_pending_action *action,
                               zend_object **first_exception)
{
    /* Verify callable */
    zend_fcall_info fci;
    zend_fcall_info_cache fcc;
    char *callable_error = NULL;

    if (zend_fcall_info_init(&action->callable, 0, &fci, &fcc,
                              NULL, &callable_error) != SUCCESS) {
        php_error_docref(NULL, E_WARNING,
            "AfterParseAction: invalid callable: %s",
            callable_error ? callable_error : "unknown error");
        if (callable_error) {
            efree(callable_error);
        }
        return;
    }
    if (callable_error) {
        efree(callable_error);
    }

    /* Build params: callable(className, methodName, ...extraArgs) */
    zval *params = emalloc(sizeof(zval) * (2 + action->extra_argc));
    uint32_t positional_count = 2;

    if (action->target_class) {
        ZVAL_STR_COPY(&params[0], action->target_class);
    } else {
        ZVAL_NULL(&params[0]);
    }

    if (action->target_method) {
        ZVAL_STR_COPY(&params[1], action->target_method);
    } else {
        ZVAL_NULL(&params[1]);
    }

    /* Split extra args into positional vs named */
    HashTable *named_params = NULL;
    for (uint32_t i = 0; i < action->extra_argc; i++) {
        if (action->extra_names[i]) {
            if (!named_params) {
                ALLOC_HASHTABLE(named_params);
                zend_hash_init(named_params, action->extra_argc, NULL,
                               ZVAL_PTR_DTOR, 0);
            }
            zval copy;
            ZVAL_COPY(&copy, &action->extra_args[i]);
            zend_hash_update(named_params, action->extra_names[i], &copy);
        } else {
            ZVAL_COPY(&params[positional_count], &action->extra_args[i]);
            positional_count++;
        }
    }

    /* Call */
    zval retval;
    ZVAL_UNDEF(&retval);
    fci.retval = &retval;
    fci.params = params;
    fci.param_count = positional_count;
    fci.named_params = named_params;

    zend_call_function(&fci, &fcc);

    /* Capture exception — save first, clear to let queue continue */
    if (EG(exception)) {
        if (!*first_exception) {
            *first_exception = EG(exception);
            GC_ADDREF(*first_exception);
        }
        zend_clear_exception();
    }

    /* Cleanup */
    zval_ptr_dtor(&retval);
    for (uint32_t i = 0; i < positional_count; i++) {
        zval_ptr_dtor(&params[i]);
    }
    efree(params);
    if (named_params) {
        zend_hash_destroy(named_params);
        FREE_HASHTABLE(named_params);
    }
}

/* --- Flush all pending actions --- */

static void apa_flush_pending(void)
{
    if (APA_G(flushing)) {
        return;
    }
    APA_G(flushing) = 1;

    zend_object *first_exception = NULL;

    while (zend_llist_count(&APA_G(pending_actions)) > 0) {
        /* Pop from head. We memcpy then unlink without calling dtor
         * because we take ownership of the copied struct's data. */
        zend_llist_element *head = APA_G(pending_actions).head;
        apa_pending_action action;
        memcpy(&action, head->data, sizeof(apa_pending_action));

        APA_G(pending_actions).head = head->next;
        if (!head->next) {
            APA_G(pending_actions).tail = NULL;
        }
        APA_G(pending_actions).count--;
        pefree(head, APA_G(pending_actions).persistent);

        apa_execute_action(&action, &first_exception);

        /* Cleanup — we own these, not the llist */
        zval_ptr_dtor(&action.callable);
        for (uint32_t i = 0; i < action.extra_argc; i++) {
            zval_ptr_dtor(&action.extra_args[i]);
            if (action.extra_names[i]) {
                zend_string_release(action.extra_names[i]);
            }
        }
        if (action.extra_args) efree(action.extra_args);
        if (action.extra_names) efree(action.extra_names);
        if (action.target_class) zend_string_release(action.target_class);
        if (action.target_method) zend_string_release(action.target_method);
    }

    APA_G(flushing) = 0;

    /* Re-throw the first captured exception after all actions fired.
     * zend_throw_exception_object steals our reference. */
    if (first_exception) {
        zval exception_zv;
        ZVAL_OBJ(&exception_zv, first_exception);
        zend_throw_exception_object(&exception_zv);
    }
}

/* --- execute_ex override --- */

static void apa_execute_ex(zend_execute_data *execute_data)
{
    original_execute_ex(execute_data);

    if (zend_llist_count(&APA_G(pending_actions)) > 0) {
        apa_flush_pending();
    }
}

/* --- Attribute scanning --- */

/**
 * Scan an attributes HashTable for ALL #[\AfterParseAction] instances.
 * zend_get_attribute_str only returns the first match, so we iterate
 * manually to support repeatable attributes.
 *
 * Attribute values are resolved and copied at queue time — no dangling
 * pointers to the attribute struct.
 */
static void apa_scan_attributes(HashTable *attributes,
                                zend_string *target_class,
                                zend_string *target_method)
{
    if (!attributes) return;

    zend_attribute *attr;
    ZEND_HASH_PACKED_FOREACH_PTR(attributes, attr) {
        if (attr->argc >= 1 &&
            ZSTR_LEN(attr->lcname) == APA_ATTR_NAME_LEN &&
            memcmp(ZSTR_VAL(attr->lcname), APA_ATTR_NAME, APA_ATTR_NAME_LEN) == 0) {
            apa_queue_action(attr, target_class, target_method);
        }
    } ZEND_HASH_FOREACH_END();
}

/* --- Observer callbacks --- */

/**
 * Scan a single ancestor (parent or interface) for method attributes that
 * the concrete class's own method doesn't carry.
 *
 * PHP propagates attributes for inherited concrete methods but NOT for:
 * - Abstract method declarations (child provides its own implementation)
 * - Interface method declarations (implementations don't inherit attributes)
 *
 * We check each ancestor method: if the concrete class has a method with
 * the same name but the concrete method lacks the attribute, fire the
 * ancestor's attribute with the concrete class name.
 */
static void apa_scan_ancestor_methods(zend_class_entry *ancestor,
                                      zend_class_entry *concrete)
{
    zend_function *ancestor_func;
    ZEND_HASH_FOREACH_PTR(&ancestor->function_table, ancestor_func) {
        if (!ancestor_func->common.attributes) continue;

        zend_string *fn_lc = zend_string_tolower(ancestor_func->common.function_name);
        zend_function *concrete_func = zend_hash_find_ptr(&concrete->function_table, fn_lc);
        zend_string_release(fn_lc);
        if (!concrete_func) continue;

        /* Skip if the concrete class inherited the method directly (same scope).
         * Its attributes were already scanned in the main loop. */
        if (concrete_func->common.scope == ancestor) continue;

        /* Only propagate from abstract/interface method declarations.
         * Concrete method overrides without the attribute are intentional. */
        if (!(ancestor_func->common.fn_flags & ZEND_ACC_ABSTRACT)) continue;

        /* No dedup: the interface and the implementation may have different
         * AfterParseAction attrs calling different callables. Both should fire.
         * If someone puts the same attr on both, they get it twice. */

        apa_scan_attributes(ancestor_func->common.attributes,
                            concrete->name, ancestor_func->common.function_name);
    } ZEND_HASH_FOREACH_END();
}

static void apa_class_linked_cb(zend_class_entry *ce, zend_string *name)
{
    /* Skip traits, abstract classes, and interfaces.
     * Traits: methods copied into using classes, processed there.
     * Abstract/Interface: their methods fire for each concrete child
     *   via the ancestor scanning below. */
    if (ce->ce_flags & (ZEND_ACC_TRAIT | ZEND_ACC_ABSTRACT | ZEND_ACC_INTERFACE)) {
        return;
    }

    /* Class-level attributes */
    apa_scan_attributes(ce->attributes, ce->name, NULL);

    /* All methods including inherited — each concrete class fires
     * with its own class name, not the declaring class. */
    zend_function *func;
    ZEND_HASH_FOREACH_PTR(&ce->function_table, func) {
        apa_scan_attributes(func->common.attributes,
                            ce->name, func->common.function_name);
    } ZEND_HASH_FOREACH_END();

    /* Walk parent chain for abstract methods whose attributes don't
     * propagate to the concrete implementation. */
    zend_class_entry *parent = ce->parent;
    while (parent) {
        apa_scan_ancestor_methods(parent, ce);
        parent = parent->parent;
    }

    /* Walk interfaces — same issue: implementations don't inherit
     * interface method attributes. */
    for (uint32_t i = 0; i < ce->num_interfaces; i++) {
        apa_scan_ancestor_methods(ce->interfaces[i], ce);
    }
}

static void apa_function_declared_cb(zend_op_array *op_array, zend_string *name)
{
    apa_scan_attributes(op_array->attributes, NULL, op_array->function_name);
}

/* --- Lifecycle --- */

void apa_handler_init(void)
{
    zend_observer_class_linked_register(apa_class_linked_cb);
    zend_observer_function_declared_register(apa_function_declared_cb);

    original_execute_ex = zend_execute_ex;
    zend_execute_ex = apa_execute_ex;
}

void apa_handler_rinit(void)
{
    zend_llist_init(&APA_G(pending_actions), sizeof(apa_pending_action),
                    pending_action_dtor, 0);
    APA_G(flushing) = 0;

    /* Scan preloaded classes already in the class table.
     * class_linked observer doesn't fire for preloaded classes. */
    zend_class_entry *ce;
    ZEND_HASH_FOREACH_PTR(EG(class_table), ce) {
        if (ce->type != ZEND_USER_CLASS) continue;
        if (!(ce->ce_flags & ZEND_ACC_LINKED)) continue;
        apa_class_linked_cb(ce, ce->name);
    } ZEND_HASH_FOREACH_END();
}

void apa_handler_rshutdown(void)
{
    zend_llist_destroy(&APA_G(pending_actions));
}
