/*
 * AfterParseAction PHP extension
 * Copyright (c) 2026 Grzegorz Drozd
 * Licensed under the AGPL-3.0 license. See LICENSE file.
 */

#ifndef PHP_APA_H
#define PHP_APA_H

#include "php.h"
#include "zend_attributes.h"

extern zend_module_entry apa_module_entry;
#define phpext_apa_ptr &apa_module_entry

#define PHP_APA_VERSION "0.1.0"

/* Pending action: queued during class linking, executed after file loads.
 * Owns copies of all data. No dangling pointers to class/function entries. */
typedef struct apa_pending_action {
    zval callable;              /* copied from attr->args[0] */
    uint32_t extra_argc;        /* attr->argc - 1 */
    zval *extra_args;           /* copied from attr->args[1..N] values */
    zend_string **extra_names;  /* copied from attr->args[1..N] names (NULL if positional) */
    zend_string *target_class;  /* class name, or NULL for global functions */
    zend_string *target_method; /* method/function name, or NULL for class-level */
} apa_pending_action;

ZEND_BEGIN_MODULE_GLOBALS(apa)
    zend_llist pending_actions;
    int flushing; /* re-entrancy guard */
ZEND_END_MODULE_GLOBALS(apa)

ZEND_EXTERN_MODULE_GLOBALS(apa)

#define APA_G(v) ZEND_MODULE_GLOBALS_ACCESSOR(apa, v)

#if defined(ZTS) && defined(COMPILE_DL_APA)
ZEND_TSRMLS_CACHE_EXTERN()
#endif

#endif /* PHP_APA_H */
