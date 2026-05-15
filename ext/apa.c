/*
 * AfterParseAction PHP extension
 * Copyright (c) 2026 Grzegorz Drozd
 * Licensed under the AGPL-3.0 license. See LICENSE file.
 */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "ext/standard/info.h"
#include "php_apa.h"
#include "apa_handler.h"
#include "zend_attributes.h"

/* --- Module lifecycle --- */

ZEND_DECLARE_MODULE_GLOBALS(apa)

PHP_GINIT_FUNCTION(apa) {
    ZEND_SECURE_ZERO(apa_globals, sizeof(*apa_globals));
}

PHP_RINIT_FUNCTION(apa) {
#if defined(ZTS) && defined(COMPILE_DL_APA)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif
    apa_handler_rinit();
    return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(apa) {
    apa_handler_rshutdown();
    return SUCCESS;
}

PHP_MINIT_FUNCTION(apa) {
#if defined(ZTS) && defined(COMPILE_DL_APA)
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    /* Register #[\AfterParseAction] as a proper internal attribute class.
     *
     * Sequence matters:
     * 1. Register the class
     * 2. Add #[Attribute(...)] annotation to it (populates ce->attributes)
     * 3. Call zend_mark_internal_attribute (reads ce->attributes)
     *
     * Without step 2, zend_mark_internal_attribute segfaults because
     * ce->attributes is NULL after zend_register_internal_class. */
    zend_class_entry ce;
    INIT_CLASS_ENTRY(ce, "AfterParseAction", NULL);
    zend_class_entry *ce_apa = zend_register_internal_class(&ce);
    ce_apa->ce_flags |= ZEND_ACC_FINAL;

    /* Add #[Attribute] annotation with target flags */
    zend_attribute *attr = zend_add_class_attribute(
        ce_apa, zend_ce_attribute->name, 1);
    ZVAL_LONG(&attr->args[0].value,
        ZEND_ATTRIBUTE_TARGET_METHOD
        | ZEND_ATTRIBUTE_TARGET_FUNCTION
        | ZEND_ATTRIBUTE_TARGET_CLASS
        | ZEND_ATTRIBUTE_IS_REPEATABLE);

    /* Now safe to call. ce->attributes is populated. */
    zend_mark_internal_attribute(ce_apa);

    /* Register observers and execute_ex override */
    apa_handler_init();

    return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(apa) {
    return SUCCESS;
}

PHP_MINFO_FUNCTION(apa) {
    php_info_print_table_start();
    php_info_print_table_row(2, "AfterParseAction support", "enabled");
    php_info_print_table_row(2, "apa version", PHP_APA_VERSION);
    php_info_print_table_end();
}

/* --- Module entry --- */

zend_module_entry apa_module_entry = {
    STANDARD_MODULE_HEADER,
    "apa",
    NULL, /* no functions */
    PHP_MINIT(apa),
    PHP_MSHUTDOWN(apa),
    PHP_RINIT(apa),
    PHP_RSHUTDOWN(apa),
    PHP_MINFO(apa),
    PHP_APA_VERSION,
    PHP_MODULE_GLOBALS(apa),
    PHP_GINIT(apa),
    NULL,
    NULL,
    STANDARD_MODULE_PROPERTIES_EX,
};

#ifdef COMPILE_DL_APA
#ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
#endif
ZEND_GET_MODULE(apa)
#endif
