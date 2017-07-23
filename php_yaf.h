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

#ifndef PHP_YAF_H
#define PHP_YAF_H

extern zend_module_entry yaf_module_entry;
#define phpext_yaf_ptr &yaf_module_entry

#ifdef PHP_WIN32
#define PHP_YAF_API __declspec(dllexport)
#ifndef _MSC_VER
#define _MSC_VER 1600
#endif
#else
#define PHP_YAF_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

/*
* how to visit globals?here  thread safe or unsafe use different ways.
* phoenix add
*/
#ifdef ZTS
#define YAF_G(v) TSRMG(yaf_globals_id, zend_yaf_globals *, v)
#else
#define YAF_G(v) (yaf_globals.v)
#endif

#define PHP_YAF_VERSION 					"3.0.5-dev"
//YAF_STARTUP_FUNCTION这个宏用来定义一个启动函数，YAF_STARTUP调用此启动函数完成类的加载。
//然后，每个类都会使用YAF_STARTUP_FUNCTION定义其启动函数
//#define ZEND_MINIT_FUNCTION			ZEND_MODULE_STARTUP_D--->ZEND_MODULE_STARTUP_D(yaf_##module)
// #define ZEND_MODULE_STARTUP_D(module)	int ZEND_MODULE_STARTUP_N(module)(INIT_FUNC_ARGS)-->int ZEND_MODULE_STARTUP_N(yaf_##module)(INIT_FUNC_ARGS)
// #define ZEND_MODULE_STARTUP_N(module)       zm_startup_##module
// int ZEND_MODULE_STARTUP_N(yaf_##module)(INIT_FUNC_ARGS) -->int zm_startup_##yaf_##module(INIT_FUNC_ARGS)

#define YAF_STARTUP_FUNCTION(module)   	ZEND_MINIT_FUNCTION(yaf_##module)
#define YAF_RINIT_FUNCTION(module)		ZEND_RINIT_FUNCTION(yaf_##module)
/*yaf的优点是,yaf的所有的框架类，不需要编译，在PHP启动的时候加载, 并常驻内存。如何做到这点呢？
* Yaf定义了一个YAF_STARTUP宏来加载类，加载类在 Module init阶段完成
* ZEND_MODULE_STARTUP_N的定义在php7.0源码php/Zend/zend_API.h(line 124)中
* phoenix
*/
//YAF_STARTUP(module)展开就是 类似于zm_startup_yaf_application(INIT_FUNC_ARGS_PASSTHRU)   phoenix add end
//#define ZEND_MODULE_STARTUP_N(module)       zm_startup_##yaf_##module
//#define ZEND_MODULE_STARTUP_N(module)       zm_startup_##module --- zm_startup_##yaf_##module(INIT_FUNC_ARGS_PASSTHRU)
#define YAF_STARTUP(module)	 		  	ZEND_MODULE_STARTUP_N(yaf_##module)(INIT_FUNC_ARGS_PASSTHRU)
#define YAF_SHUTDOWN_FUNCTION(module)  	ZEND_MSHUTDOWN_FUNCTION(yaf_##module)
#define YAF_SHUTDOWN(module)	 	    ZEND_MODULE_SHUTDOWN_N(yaf_##module)(INIT_FUNC_ARGS_PASSTHRU)

#define yaf_application_t	zval
#define yaf_view_t 			zval
#define yaf_controller_t	zval
#define yaf_request_t		zval
#define yaf_router_t		zval
#define yaf_route_t			zval
#define yaf_dispatcher_t	zval
#define yaf_action_t		zval
#define yaf_loader_t		zval
#define yaf_response_t		zval
#define yaf_config_t		zval
#define yaf_registry_t		zval
#define yaf_plugin_t		zval
#define yaf_session_t		zval
#define yaf_exception_t		zval

#define YAF_ME(c, m, a, f) {m, PHP_MN(c), a, (uint) (sizeof(a)/sizeof(struct _zend_arg_info)-1), f},

extern PHPAPI void php_var_dump(zval **struc, int level);
extern PHPAPI void php_debug_zval_dump(zval **struc, int level);
/* yaf application配置,针对单个应用的配置,配置项如下:
名称											值类型	默认值									说明
application.directory						String	应用的绝对目录路径
application.ext								String	php										PHP脚本的扩展名
application.bootstrap						String	Bootstrapplication.php					Bootstrap路径(绝对路径)
application.library							String	application.directory + "/library"		本地(自身)类库的绝对目录地址
application.baseUri							String	NULL									在路由中, 需要忽略的路径前缀, 一般不需要设置, Yaf会自动判断.
application.dispatcher.defaultModule		String	index									默认的模块
application.dispatcher.throwException		Bool	TRUE									在出错的时候, 是否抛出异常
application.dispatcher.catchException		Bool	FALSE									是否使用默认的异常捕获Controller, 如果开启, 在有未捕获的异常的时候, 控制权会交给ErrorController的errorAction方法, 可以通过$request->getException()获得此异常对象
application.dispatcher.defaultController	String	index									默认的控制器
application.dispatcher.defaultAction		String	index									默认的动作
application.view.ext						String	phtml									视图模板扩展名
application.modules							String	Index									声明存在的模块名, 请注意, 如果你要定义这个值, 一定要定义Index Module
application.system.*						String	*										通过这个属性, 可以修改yaf的runtime configure, 比如application.system.lowcase_path, 但是请注意只有PHP_INI_ALL的配置项才可以在这里被修改, 此选项从2.2.0开始引入
*	application的配置保存在yaf的全局变量中,全局变量的定义如下面
*	ZEND_BEGIN_MODULE_GLOBALS(php-7.1.3/Zend/Zend_API.h line147)
*   展开后的形式:
*	typedef struct _zend_yaf_globals {
*    	unsigned long counter;
*	} zend_yaf_globals;
*   phoenix
	//##module##指的就是变量比如yaf??
	//typedef struct _zend_##module_name##_globals {
	//
	//} zend_##module_name##_globals;
*/
ZEND_BEGIN_MODULE_GLOBALS(yaf)
	zend_string	*ext;
	zend_string *base_uri;   //可以通过yaf_globals.base_uri来访问
	zend_string *directory;
	zend_string *local_library;
	zend_string *local_namespaces;
	zend_string *view_directory;
	zend_string *view_ext;
	zend_string *default_module;
	zend_string *default_controller;
	zend_string *default_action;
	zend_string *bootstrap;
	char         *global_library;
    char         *environ_name;
    char         *name_separator;
    size_t        name_separator_len;
	zend_bool 	lowcase_path;
	zend_bool 	use_spl_autoload;
	zend_bool 	throw_exception;
	zend_bool   action_prefer;
	zend_bool	name_suffix;
	zend_bool  	autoload_started;
	zend_bool  	running;
	zend_bool  	in_exception;
	zend_bool  	catch_exception;
	zend_bool   suppressing_warning;
/* {{{ This only effects internally */
	zend_bool  	st_compatible;
/* }}} */
	long		forward_limit;
	HashTable	*configs;
	zval 		 modules;
	zval        *default_route;
	zval        active_ini_file_section;
	zval        *ini_wanted_section;
	uint        parsing_flag;
	zend_bool	use_namespace;
ZEND_END_MODULE_GLOBALS(yaf)


//PHP_MINIT/_MSHUTDOWN/_RINIT/_RSHUTDOWN/_MINFO_FUNCTION等函数,定义在php7.0的源码php7.0/Zend/zend_API.h中.Mphoenix
PHP_MINIT_FUNCTION(yaf);
PHP_MSHUTDOWN_FUNCTION(yaf);
PHP_RINIT_FUNCTION(yaf);
PHP_RSHUTDOWN_FUNCTION(yaf);
PHP_MINFO_FUNCTION(yaf);
//ZEND_DECLARE_MODULE_GLOBALS 来实例化上边定义的结构体
//这里说的是线程非安全的情况，线程安全的情况另当别论.
//init module is in  yaf_application.c  yaf_application_parse_option
extern ZEND_DECLARE_MODULE_GLOBALS(yaf);

#endif

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
