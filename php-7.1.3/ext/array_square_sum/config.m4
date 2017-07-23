dnl $Id$
dnl config.m4 for extension array_square_sum

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

PHP_ARG_WITH(array_square_sum, for array_square_sum support,
Make sure that the comment is aligned:
[  --with-array_square_sum             Include array_square_sum support])

dnl Otherwise use enable:

dnl PHP_ARG_ENABLE(array_square_sum, whether to enable array_square_sum support,
dnl Make sure that the comment is aligned:
dnl [  --enable-array_square_sum           Enable array_square_sum support])

if test "$PHP_ARRAY_SQUARE_SUM" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-array_square_sum -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/array_square_sum.h"  # you most likely want to change this
  dnl if test -r $PHP_ARRAY_SQUARE_SUM/$SEARCH_FOR; then # path given as parameter
  dnl   ARRAY_SQUARE_SUM_DIR=$PHP_ARRAY_SQUARE_SUM
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for array_square_sum files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       ARRAY_SQUARE_SUM_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$ARRAY_SQUARE_SUM_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the array_square_sum distribution])
  dnl fi

  dnl # --with-array_square_sum -> add include path
  dnl PHP_ADD_INCLUDE($ARRAY_SQUARE_SUM_DIR/include)

  dnl # --with-array_square_sum -> check for lib and symbol presence
  dnl LIBNAME=array_square_sum # you may want to change this
  dnl LIBSYMBOL=array_square_sum # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $ARRAY_SQUARE_SUM_DIR/$PHP_LIBDIR, ARRAY_SQUARE_SUM_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_ARRAY_SQUARE_SUMLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong array_square_sum lib version or lib not found])
  dnl ],[
  dnl   -L$ARRAY_SQUARE_SUM_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(ARRAY_SQUARE_SUM_SHARED_LIBADD)

  PHP_NEW_EXTENSION(array_square_sum, array_square_sum.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
fi
