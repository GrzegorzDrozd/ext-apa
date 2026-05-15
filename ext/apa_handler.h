/*
 * AfterParseAction PHP extension
 * Copyright (c) 2026 Grzegorz Drozd
 * Licensed under the AGPL-3.0 license. See LICENSE file.
 */

#ifndef APA_HANDLER_H
#define APA_HANDLER_H

#include "php.h"

/* Register class_linked/function_declared callbacks and execute_ex override (MINIT) */
void apa_handler_init(void);

/* Per-request lifecycle (RINIT/RSHUTDOWN) */
void apa_handler_rinit(void);
void apa_handler_rshutdown(void);

#endif /* APA_HANDLER_H */
