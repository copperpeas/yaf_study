<?php
/**
 * @name BaseController
 * @author shilun1
 * @desc base controller, all controller extends from this controller.
 * @see http://www.php.net/manual/en/class.yaf-controller-abstract.php
 */
class BaseController extends Yaf\Controller_Abstract {
	public $params;
	public $sysConfig;

	public function init() {
	    $this->getSysConfig();
	}

	public function getSysConfig(){
		$this->params=Yaf\Registry::get("params");
		$this->sysConfig['adConfig']=Yaf\Registry::get("adConfig")->ad;
		$this->sysConfig['channeldataConfig']=Yaf\Registry::get("channeldataConfig");
		// var_dump($this->params);
		// var_dump($this->sysConfig);
	}

	public function debug($obj='',$paramsStr='dodebug'){
		if(!empty($this->params['debug'])&&$this->params['debug']==$paramsStr){
			print_r($obj);exit;
		}
	}

	public function error($msg){
		echo json_encode(array('status'=>1,'msg'=>$msg));exit;
	}

}
