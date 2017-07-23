/*
  +----------------------------------------------------------------------+
  | Yet Another Framework                                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_01.txt                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author: Xinchen Hui  <laruence@php.net>                              |
  +----------------------------------------------------------------------+
*/

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "main/SAPI.h"
#include "Zend/zend_alloc.h"
#include "ext/standard/info.h"
#include "ext/standard/php_string.h"

#include "php_yaf.h"
#include "yaf_logo.h"
#include "yaf_loader.h"
#include "yaf_exception.h"
#include "yaf_application.h"
#include "yaf_dispatcher.h"
#include "yaf_config.h"
#include "yaf_view.h"
#include "yaf_controller.h"
#include "yaf_action.h"
#include "yaf_request.h"
#include "yaf_response.h"
#include "yaf_router.h"
#include "yaf_bootstrap.h"
#include "yaf_plugin.h"
#include "yaf_registry.h"
#include "yaf_session.h"

//phoenix add start1
/*
一个PHP程序，依次经过Module init、Request init、Request shutdown、Module shutdown四个过程，
当然，之间还会执行脚本自身的代码。在命令行模式下运行一个PHP程序的主要流程如图4-1所示：
1.					$php test.php
2.module init : 	call each extension''s MINIT(PHP_MINIT_FUNCTION,在扩展被载入时调用)
3.					request test.php 			(请求到达后,php会初始化执行脚本的基本环境,包括保存php运行过程中的变量名称和变量值内容的符号表,以及当前所有的函数以及类等信息的符号表)
4.Request init: 	call each extension''s RINIT(PHP_RINIT_FUNCTION,PHP会调用所有模块的RINIT函数)
5.					Execute test.php  			(执行阶段,把PHP文件编译成Opcodes,然后在PHP虚拟机下执行)
6.Request shutdown: call each extension''s RSHUTDOWN(PHP_RSHUTDOWN_FUNCTION,请求处理完成后进入结束阶段,一般脚本执行到末尾或者通过调用exit()/die()函数,PHP都将进入结束阶段.和开始阶段对应,结束阶段也分为两个环节,一个在请求结束后(RSHUTDOWN),一个在SAPI声明周期结束时(MSHUTDOWN))
7.					Finish cleaning up after test.php
8.Module shutdown:  call each extension''s MSHUTDOWN
9.					Terminate test.php
*/
//phoenix add end1

/*php_yaf.h中的函数,针对单个应用的yaf的全局变量的配置.这里说的是线程非安全的情况，线程安全的情况另当别论.
* 展开后变成这个:zend_yaf_globals yaf_globals;
*
*/
ZEND_DECLARE_MODULE_GLOBALS(yaf);

/* {{{ yaf_functions[]
*/
zend_function_entry yaf_functions[] = {
	{NULL, NULL, NULL}
};
/* }}} */

/** {{{ PHP_INI_MH(OnUpdateSeparator)
 */
PHP_INI_MH(OnUpdateSeparator) {
	YAF_G(name_separator) = ZSTR_VAL(new_value);
	YAF_G(name_separator_len) = ZSTR_LEN(new_value);
	return SUCCESS;
}
/* }}} */

/** {{{ PHP_INI
 */
//用 PHP_INI_ENTRY() 来创建具体的配置项,并且标识开始.
//PHP_NII_ENTRY来创建具体的配置项.phoenix copperPeas
//STD_PHP_INI_ENTRY("yaf.library"(名称),	""(默认值),PHP_INI_ALL(是否允许被修改以及能被修改的作用域), OnUpdateString, global_library, zend_yaf_globals, yaf_globals(回调函数,ini值被修改的时候调用))
//Yaf是如何读取配置文件，并初始化这些参数呢？读取配置文件之前，得定义好参数，即声明变量来保存参数的值:
//PHP_INI_BEGIN() 宏来标识开始.见php源代码:php-7.1.3/main/php_ini.h(LINE56)-->php-7.1.3/zend/zend_ini.h(LINE96):
//static const zend_ini_entry_def ini_entries[] = {		//PHP_INI_BEGIN()
//{ NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0} };//PHP_INI_END()
PHP_INI_BEGIN()
	STD_PHP_INI_ENTRY("yaf.library",         	"",  PHP_INI_ALL, OnUpdateString, global_library, zend_yaf_globals, yaf_globals)//phphoenix add. STD_PHP_INI_ENTRY在php-7.1.3/main/php_ini.h line 68
	STD_PHP_INI_BOOLEAN("yaf.action_prefer",   	"0", PHP_INI_ALL, OnUpdateBool, action_prefer, zend_yaf_globals, yaf_globals)	//同上,line 70
	STD_PHP_INI_BOOLEAN("yaf.lowcase_path",    	"0", PHP_INI_ALL, OnUpdateBool, lowcase_path, zend_yaf_globals, yaf_globals)
	STD_PHP_INI_BOOLEAN("yaf.use_spl_autoload", "0", PHP_INI_ALL, OnUpdateBool, use_spl_autoload, zend_yaf_globals, yaf_globals)
	STD_PHP_INI_ENTRY("yaf.forward_limit", 		"5", PHP_INI_ALL, OnUpdateLongGEZero, forward_limit, zend_yaf_globals, yaf_globals)
	STD_PHP_INI_BOOLEAN("yaf.name_suffix", 		"1", PHP_INI_ALL, OnUpdateBool, name_suffix, zend_yaf_globals, yaf_globals)
	PHP_INI_ENTRY("yaf.name_separator", 		"",  PHP_INI_ALL, OnUpdateSeparator)
	//yaf.name_separator标识INI设置的名称,""指的是默认值,PHP_INI_ALL是否允许被修改以及能被修改的作用域,onUpdateSeparator是哥回调函数,当INI的值被修改的时候触发此回调函数.
	//PHP_INI_ENTRY宏来创建具体的配置项.
/* {{{ This only effects internally */
	STD_PHP_INI_BOOLEAN("yaf.st_compatible",     "0", PHP_INI_ALL, OnUpdateBool, st_compatible, zend_yaf_globals, yaf_globals)
/* }}} */
	STD_PHP_INI_ENTRY("yaf.environ",        	"product", PHP_INI_SYSTEM, OnUpdateString, environ_name, zend_yaf_globals, yaf_globals)
	STD_PHP_INI_BOOLEAN("yaf.use_namespace",   	"0", PHP_INI_SYSTEM, OnUpdateBool, use_namespace, zend_yaf_globals, yaf_globals)
PHP_INI_END();
//PHP_INI_END()来标识配置节的结束
/* }}} */

/*上面定义好的结构体如下:phphoenix add start
static zend_ini_entry ini_entries[] = { //BEGIN 的定义
	{ 0, PHP_INI_ALL, "yaf.library", sizeof("yaf.library"), NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL},
	...
	{ 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL } }; // END的定义

选项名称	                默认值	可修改范围		说明
yaf.environ	            product	PHP_INI_ALL		环境名称, 当用INI作为Yaf的配置文件时, 这个指明了Yaf将要在INI配置中读取的节的名字
yaf.library	            NULL	PHP_INI_ALL		全局类库的目录路径
yaf.cache_config	    0	    PHP_INI_SYSTEM	是否缓存配置文件(只针对INI配置文件生效), 打开此选项可在复杂配置的情况下提高性能
yaf.name_suffix	        1	    PHP_INI_ALL		在处理Controller, Action, Plugin, Model的时候, 类名中关键信息是否是后缀式, 比如UserModel, 而在前缀模式下则是ModelUser
yaf.name_separator	    ""	    PHP_INI_ALL		在处理Controller, Action, Plugin, Model的时候, 前缀和名字之间的分隔符, 默认为空, 也就是UserPlugin, 加入设置为"_", 则判断的依据就会变成:"User_Plugin", 这个主要是为了兼容ST已有的命名规范
yaf.forward_limit	    5	    PHP_INI_ALL		forward最大嵌套深度
yaf.use_namespace	    0	    PHP_INI_ALL		开启的情况下, Yaf将会使用命名空间方式注册自己的类, 比如Yaf_Application将会变成Yaf\Application
yaf.use_spl_autoload	0	    PHP_INI_SYSTEM	开启的情况下, Yaf在加载不成功的情况下, 会继续让PHP的自动加载函数加载, 从性能考虑, 除非特殊情况, 否则保持这个选项关闭

phoenix add end*/

/** {{{ PHP_GINIT_FUNCTION
*/
PHP_GINIT_FUNCTION(yaf)
{
	memset(yaf_globals, 0, sizeof(*yaf_globals));
}
/* }}} */

/** {{{ PHP_MINIT_FUNCTION
*/
//phoenix add start2 在扩展被载入时调用  注册常量或者类等初始化操作  phoenix add end start2
PHP_MINIT_FUNCTION(yaf)
{
	REGISTER_INI_ENTRIES();//读取上面PHP_INI_BEGIN()和PHP_INI_END()之间的ini参数填充到init_entries结构体.见php源代码.

	if (YAF_G(use_namespace)) {

		REGISTER_STRINGL_CONSTANT("YAF\\VERSION", PHP_YAF_VERSION, 	sizeof(PHP_YAF_VERSION) - 1, CONST_PERSISTENT | CONST_CS);
		REGISTER_STRINGL_CONSTANT("YAF\\ENVIRON", YAF_G(environ_name), strlen(YAF_G(environ_name)), CONST_PERSISTENT | CONST_CS);

		REGISTER_LONG_CONSTANT("YAF\\ERR\\STARTUP_FAILED", 		YAF_ERR_STARTUP_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\ROUTE_FAILED", 		YAF_ERR_ROUTE_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\DISPATCH_FAILED", 	YAF_ERR_DISPATCH_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\AUTOLOAD_FAILED", 	YAF_ERR_AUTOLOAD_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\NOTFOUND\\MODULE", 	YAF_ERR_NOTFOUND_MODULE, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\NOTFOUND\\CONTROLLER",YAF_ERR_NOTFOUND_CONTROLLER, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\NOTFOUND\\ACTION", 	YAF_ERR_NOTFOUND_ACTION, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\NOTFOUND\\VIEW", 		YAF_ERR_NOTFOUND_VIEW, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\CALL_FAILED",			YAF_ERR_CALL_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF\\ERR\\TYPE_ERROR",			YAF_ERR_TYPE_ERROR, CONST_PERSISTENT | CONST_CS);

	} else {
		REGISTER_STRINGL_CONSTANT("YAF_VERSION", PHP_YAF_VERSION, 	sizeof(PHP_YAF_VERSION) - 1, 	CONST_PERSISTENT | CONST_CS);
		REGISTER_STRINGL_CONSTANT("YAF_ENVIRON", YAF_G(environ_name),strlen(YAF_G(environ_name)), 	CONST_PERSISTENT | CONST_CS);

		REGISTER_LONG_CONSTANT("YAF_ERR_STARTUP_FAILED", 		YAF_ERR_STARTUP_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_ROUTE_FAILED", 			YAF_ERR_ROUTE_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_DISPATCH_FAILED", 		YAF_ERR_DISPATCH_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_AUTOLOAD_FAILED", 		YAF_ERR_AUTOLOAD_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_NOTFOUND_MODULE", 		YAF_ERR_NOTFOUND_MODULE, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_NOTFOUND_CONTROLLER", 	YAF_ERR_NOTFOUND_CONTROLLER, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_NOTFOUND_ACTION", 		YAF_ERR_NOTFOUND_ACTION, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_NOTFOUND_VIEW", 		YAF_ERR_NOTFOUND_VIEW, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_CALL_FAILED",			YAF_ERR_CALL_FAILED, CONST_PERSISTENT | CONST_CS);
		REGISTER_LONG_CONSTANT("YAF_ERR_TYPE_ERROR",			YAF_ERR_TYPE_ERROR, CONST_PERSISTENT | CONST_CS);
	}

	/*yaf的优点是,yaf的所有的框架类，不需要编译，在PHP启动的时候加载, 并常驻内存。如何做到这点呢？
	* Yaf定义了一个YAF_STARTUP宏来加载类，加载类在 Module init阶段完成,YAF_STARTUP在php_yaf.h(line 54)中:
	* phoenix
	*/
	/* startup components */
	YAF_STARTUP(application); //YAF_STARTUP接受一个参数,可以理解为类名,然后加载这个类.zm_activate_##module
	YAF_STARTUP(bootstrap);
	YAF_STARTUP(dispatcher);
	YAF_STARTUP(loader);
	YAF_STARTUP(request);
	YAF_STARTUP(response);
	YAF_STARTUP(controller);
	YAF_STARTUP(action);
	YAF_STARTUP(config);
	YAF_STARTUP(view);
	YAF_STARTUP(router);
	YAF_STARTUP(plugin);
	YAF_STARTUP(registry);
	YAF_STARTUP(session);
	YAF_STARTUP(exception);
	//PHOENIX ADD
	/*
	查看YAF_STARTUP和YAF_STARTUP_FUNCTION的宏定义可以知道这两个宏最终都是定位到同一个函数.
	可以看到YAF_STARTUP_FUNCTION这个宏用来定义一个启动函数，YAF_STARTUP调用此启动函数完成类的加载。然后，每个类都会使用YAF_STARTUP_FUNCTION定义其启动函数，例如，我们来看router类的启动函数。
	*/
	//PHOENIX END
	return SUCCESS;
}
/* }}} */

/** {{{ PHP_MSHUTDOWN_FUNCTION
*/
PHP_MSHUTDOWN_FUNCTION(yaf)
{
	UNREGISTER_INI_ENTRIES();

	if (YAF_G(configs)) {
		zend_hash_destroy(YAF_G(configs));
		pefree(YAF_G(configs), 1);
	}

	return SUCCESS;
}
/* }}} */

/** {{{ PHP_RINIT_FUNCTION
*/
/*
* init globals  php在Request init阶段初始化全局变量,会调用所有模块的RINIT函数.
* phoenix add
*/
PHP_RINIT_FUNCTION(yaf)
{
	YAF_G(throw_exception) = 1;
	YAF_G(ext) = zend_string_init(YAF_DEFAULT_EXT, sizeof(YAF_DEFAULT_EXT) - 1, 0);
	YAF_G(view_ext) = zend_string_init(YAF_DEFAULT_VIEW_EXT, sizeof(YAF_DEFAULT_VIEW_EXT) - 1, 0);
	YAF_G(default_module) = zend_string_init(
			YAF_ROUTER_DEFAULT_MODULE, sizeof(YAF_ROUTER_DEFAULT_MODULE) - 1, 0);
	YAF_G(default_controller) = zend_string_init(
			YAF_ROUTER_DEFAULT_CONTROLLER, sizeof(YAF_ROUTER_DEFAULT_CONTROLLER) - 1, 0);
	YAF_G(default_action) = zend_string_init(
			YAF_ROUTER_DEFAULT_ACTION, sizeof(YAF_ROUTER_DEFAULT_ACTION) - 1, 0);
	return SUCCESS;
}
/* }}} */

/** {{{ PHP_RSHUTDOWN_FUNCTION
*/
//phoenix add. Request shutdown阶段的函数PHP_RSHUTDOWN_FUNCTION,请求处理完成之后进入结束阶段,一般脚本执行到末尾或者通过调用die()/exit(),php都将进入结束阶段
//结束阶段也分为两个环节,一个在请求结束后(RSHUTDOWN),一个在SAPI生命周期结束时(MSHUTDOWN)(SAPI声明周期结束是神码?????)
PHP_RSHUTDOWN_FUNCTION(yaf)
{
	YAF_G(running) = 0;
	YAF_G(in_exception)	= 0;
	YAF_G(catch_exception) = 0;

	if (YAF_G(directory)) {
		zend_string_release(YAF_G(directory));
		YAF_G(directory) = NULL;
	}
	if (YAF_G(local_library)) {
		zend_string_release(YAF_G(local_library));
		YAF_G(local_library) = NULL;
	}
	if (YAF_G(local_namespaces)) {
		zend_string_release(YAF_G(local_namespaces));
		YAF_G(local_namespaces) = NULL;
	}
	if (YAF_G(bootstrap)) {
		zend_string_release(YAF_G(bootstrap));
		YAF_G(bootstrap) = NULL;
	}
	if (Z_TYPE(YAF_G(modules)) == IS_ARRAY) {
		zval_ptr_dtor(&YAF_G(modules));
		ZVAL_UNDEF(&YAF_G(modules));
	}
	if (YAF_G(base_uri)) {
		zend_string_release(YAF_G(base_uri));
		YAF_G(base_uri) = NULL;
	}
	if (YAF_G(view_directory)) {
		zend_string_release(YAF_G(view_directory));
		YAF_G(view_directory) = NULL;
	}
	if (YAF_G(view_ext)) {
		zend_string_release(YAF_G(view_ext));
	}
	if (YAF_G(default_module)) {
		zend_string_release(YAF_G(default_module));
	}
	if (YAF_G(default_controller)) {
		zend_string_release(YAF_G(default_controller));
	}
	if (YAF_G(default_action)) {
		zend_string_release(YAF_G(default_action));
	}
	if (YAF_G(ext)) {
		zend_string_release(YAF_G(ext));
	}
	YAF_G(default_route) = NULL;

	return SUCCESS;
}
/* }}} */

/** {{{ PHP_MINFO_FUNCTION
*/
PHP_MINFO_FUNCTION(yaf)
{
	php_info_print_table_start();
	if (PG(expose_php) && !sapi_module.phpinfo_as_text) {
		php_info_print_table_header(2, "yaf support", YAF_LOGO_IMG"enabled");
	} else {
		php_info_print_table_header(2, "yaf support", "enabled");
	}

	php_info_print_table_row(2, "Version", PHP_YAF_VERSION);
	php_info_print_table_row(2, "Supports", YAF_SUPPORT_URL);
	php_info_print_table_end();

	DISPLAY_INI_ENTRIES();
}
/* }}} */

/** {{{ DL support
 */
#ifdef COMPILE_DL_YAF
ZEND_GET_MODULE(yaf)
#endif
/* }}} */

/** {{{ module depends
 */
#if ZEND_MODULE_API_NO >= 20050922
zend_module_dep yaf_deps[] = {
	ZEND_MOD_REQUIRED("spl")
	ZEND_MOD_REQUIRED("pcre")
	ZEND_MOD_OPTIONAL("session")
	{NULL, NULL, NULL}
};
#endif
/* }}} */

/** {{{ yaf_module_entry
*/
zend_module_entry yaf_module_entry = {
#if ZEND_MODULE_API_NO >= 20050922
	STANDARD_MODULE_HEADER_EX, NULL,
	yaf_deps,
#else
	STANDARD_MODULE_HEADER,
#endif
	"yaf",
	yaf_functions,
	PHP_MINIT(yaf),
	PHP_MSHUTDOWN(yaf),
	PHP_RINIT(yaf),
	PHP_RSHUTDOWN(yaf),
	PHP_MINFO(yaf),
	PHP_YAF_VERSION,
	PHP_MODULE_GLOBALS(yaf),
	PHP_GINIT(yaf),
	NULL,
	NULL,
	STANDARD_MODULE_PROPERTIES_EX
};
/* }}} */

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
