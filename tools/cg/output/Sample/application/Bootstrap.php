<?php
/**
 * @name Bootstrap
 * @author shilun1
 * @desc 所有在Bootstrap类中, 以_init开头的方法, 都会被Yaf调用,
 * @see http://www.php.net/manual/en/class.yaf-bootstrap-abstract.php
 * 这些方法, 都接受一个参数:Yaf_Dispatcher $dispatcher
 * 调用的次序, 和申明的次序相同
 */
class Bootstrap extends Yaf\Bootstrap_Abstract{

    public function _initConfig() {
		//把配置保存起来
		$arrConfig = Yaf\Application::app()->getConfig();
		Yaf\Registry::set('config', $arrConfig);
		$paramsConfig =  new Yaf\Config\Ini(APPLICATION_PATH.'/conf/app/paramsConfig.ini');
		Yaf\Registry::set('paramsConfig',$paramsConfig);
		// print_r(json_encode($paramsConfig->toArray()['paramsField']));
		$appConfig = new  Yaf\Config\Ini(APPLICATION_PATH.'/conf/app/appConfig.ini');
		Yaf\Registry::set('appConfig',$appConfig);
		// var_dump($appConfig->toArray());
		// print_r(json_encode($appConfig->toArray()));//['paramsField']));

	}
	//处理并设定参数.
	public function _initParams(){
		// var_dump($this->paramsConfig);
		$item = array();
		$paramsConfig =  Yaf\Registry::get('paramsConfig')->paramsField->toArray();
		// var_dump($paramsConfig);
		//$_GET or $_POST  must  being  only one.
		foreach($_GET as $Gkey => $Gval){
			#code
			$item[$Gkey] = $Gval;
		}
		foreach($_POST as $Pkey=>$Pval){
			#code
			$item[$Pkey] = $Pval;
		}

		//过 滤 掉 不存在于配置文件中的参数.
		foreach ($paramsConfig as $key => $value) {
			# code...
			if(isset($item[$key]))
				$params[$key] =  $item[$key];//value;
		}

		// var_dump($_SERVER['PHP_SELF']);
		// var_dump($_SERVER['QUERY_STRING']);
		// var_dump($_SERVER['HTTP_HOST']);
		//cut  params from's  last 4 number
		$resource = explode('/',$params['resource']);
		$params['controller'] = $resource[0];
		$params['action'] = empty($resource[1])?'index':$resource[1];
		$osType = substr($params['from'],-4);

		switch($osType){
			case '5012': $params['platform'] = $platform = 'Android';break;
			case '3012': $params['platform'] = $platform = 'Iphone';break;
			default: $params['platform'] = $platform = 'Android';break;
		}

		// var_dump($params);
		//thereis a notice     phoenix           
		$params['apiUrl'] .= 'http://'.$_SERVER['HTTP_HOST'].str_replace('/index.php','/',$_SERVER['PHP_SELF']).'?'.$_SERVER['QUERY_STRING'];
		// var_dump($params['apiUrl']);
		Yaf\Registry::set("params",$params);
	}
	public function _initPlugin(Yaf\Dispatcher $dispatcher) {
		// var_dump(__LINE__,__FUNCTION__);
		//注册一个插件
		// $objSamplePlugin = new SamplePlugin();
		// $dispatcher->registerPlugin($objSamplePlugin);
	}

	public function _initRoute(Yaf\Dispatcher $dispatcher) {
		$rtParams = Yaf\Registry::get("params");
		$platform 	= $rtParams['platform'];
		$action 	= $rtParams['action'];
		$controller	= $rtParams['controller'];
		$routePath = '_'.$platform.'_'.$controller;
		$config = array(
				'name'=>array(
					'type'=>'rewrite',
					'match'=>'*',
					'route'=>array(
						'controller'=>'Version_Vbase'.$routePath,//$platform.$controller, // like vbase_android_msg
						'action'=>$action,
						),
					),
			);
		Yaf\Dispatcher::getInstance()->getRouter()->addConfig(new Yaf\Config\Simple($config));
		// var_dump(Yaf\Dispatcher::getInstance()->getRouter());
		// var_dump($platform);

		//route  means   we get  way from the explanation of requrest.
		//
		/*$config = array(
		        "name" => array(
		           "type"  => "rewrite",        //Yaf\Route_Rewrite route
		           "match" => "*",
		           "route" => array(
		               'controller' => "Vbase".$resource,  //route to user controller,
		               'action'     => !empty($resourceArr[1])?$resourceArr[1]:"index",  //todo
		           ),
		       ),
	  	 );
		 Yaf\Dispatcher::getInstance()->getRouter()->addConfig(
		        new Yaf\Config\Simple($config)
		 );
*/
		//在这里注册自己的路由协议,默认使用简单路由
		// var_dump(Yaf_Router::getCurrentRoute());
		// var_dump(Yaf_Router::getCurrentRoute());
		// var_dump(Yaf_Router::getInstance()->route());
		// var_dump(Yaf_Router::getRoutes());
		// var_dump(Yaf_Dispatcher::getInstance()->getRouter());
		// var_dump(Yaf_Dispatcher::getInstance()->getRequest());
		// var_dump(Yaf_Application::getConfig());
		// var_dump(Yaf_Dispatcher::getInstance()->getCurrentRoute());//Application::getConfig());
		// var_dump(Yaf_Dispatcher::getInstance()->getRouter());//Application::getConfig());
		// var_dump(Yaf_Dispatcher::getInstance()->getRequest());//Application::getConfig());
	}

	public function _initView(Yaf\Dispatcher $dispatcher){
		echo "4\r\n";
		// var_dump($dispatcher);
		// var_dump($dispatcher->getRequest());
		//在这里注册自己的view控制器，例如smarty,firekylin
	}
}
