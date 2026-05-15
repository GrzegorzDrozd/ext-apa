PHP_ARG_ENABLE([apa],
  [whether to enable apa (AfterParseAction) support],
  [AS_HELP_STRING([--enable-apa],
    [Enable AfterParseAction attribute support])],
  [no])

if test "$PHP_APA" != "no"; then
  AC_DEFINE(HAVE_APA, 1, [ Have AfterParseAction support ])
  PHP_NEW_EXTENSION(apa, apa.c apa_handler.c, $ext_shared,, "-Wall -Wextra -Werror -Wno-unused-parameter")
fi
