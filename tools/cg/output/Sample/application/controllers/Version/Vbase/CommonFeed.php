<?php
class Version_Vbase_CommonFeedController extends BaseController
{
    protected $Service_Feed_FeedModel = false;
    protected $pageSize = 10;
    protected $focusPageSize = 5;
    protected $isFocus = false;
    protected $channel = null;
    protected $pull_direction = 'down';
    protected $pull_times = 1;
    protected $page = 1;
    protected $pull_down_recommend = false; //标识是否下拉
    protected $replacedFlag = 0; //标识首屏是否被替换(下拉操作)
    protected $feedLastIndex = 20; //feed流最后1条index
    protected $hasFocus=false; //是否已有焦点图
    protected $tuijian_offset = 0;
    protected $tuijian_length = 6;
    protected $tuijianCache = true; //是否开启推荐cache
    protected $allFeedCache = false;//feed整个缓存
    protected $downEnable = false; //是否支持下拉
    protected $finance_down_enable = false;//是否开启财经下拉
    protected $video_down_enable = false;//是否开启视频下拉
    protected $ent_down_enable = false;//开启视频频道下拉

    //toutiao外其他频道接入天乙
    public static $tinayiChannel = array(
        'news_finance',
        'news_video',
        'news_tech',
        'news_sports',
        'news_auto',
        'news_ent',
        'news_sh',
        'news_pic',
        'news_mil',
        'news_fashion',
        'news_star',
        'local_beijing',
        'news_eladies',
        'news_edu',
        'news_baby'
    );

    /**
     *
     */
    public function indexAction()
    {
        var_dump(__FUNCTION__,__LINE__);
        if(empty($this->params['channel'])){
            $this->params['channel']='news_toutiao';
        }

        //天乙灰度 重置频道名称
        if($this->params['channel']=='news_toutiao' && Business_Feed_RecommendModel::isTianyiUser($this->params)){
            $this->params['channel']='news_tianyi';
        }

        $version = intval($this->params['versionNum']);

        if($this->params['channel']=='news_toutiao'){
            $this->params['channel']='news_news';
        }elseif($this->params['channel']=='news_tianyi'){
            $tianyi_obj = new Version_Vbase_TianyiFeedController();
            $tianyi_obj->params = $this->params;
            $tianyi_obj->tianyiFeed();
            exit;
        }

    var_dump(__FUNCTION__,__LINE__);
        //取公共配置
        $common_config = \Comos\Smartdata::getsmartdata('客户端公共配置',500,600);
        var_dump($common_config);
        //非头条的接天乙数据频道控制
        $tychannel = $common_config['data'][0]['tychannel'];
//        $tychannel_arr = explode(',',$tychannel);
        if(empty($tychannel_arr[0])){
            $tychannel_arr = self::$tinayiChannel;
        }
        //审核时用，图集切换数据
        if($this->params['channel']=='news_pic'&&$this->params['platform']=='iphone'){
            $aduitVersion=Service_Audit_IphoneModel::auditVersion();
            if( !empty($aduitVersion) && $this->params['versionNum']==$aduitVersion) {
                $tychannel_arr_temp = array_flip($tychannel_arr);
                unset($tychannel_arr_temp['news_pic']);
                $tychannel_arr = array_flip($tychannel_arr_temp);
            }
        }
        //配置有数据，以配置数据为主，赋给默认
        if(!empty($tychannel_arr[0])){
            self::$tinayiChannel = $tychannel_arr;
        }

        if(in_array($this->params['channel'],$tychannel_arr) && $version>=530){
            $tianyi_common_obj = new Version_Vbase_TianyiCommonFeedController();
            $tianyi_common_obj->params = $this->params;
            $tianyi_common_obj->tianyiFeed();
            exit;
        }elseif(strpos($this->params['channel'],'local_')!==false && $version>=540){ //地方站由城市channel切换为数据channel（目前为省级）
            $this->params['originalChannel'] = $this->params['channel'];
            $this->params['channel'] = Service_Local_LocalModel::getLocalDataChannel($this->params['channel']);
        }
        Business_Feed_VbaseModel::$channel = $this->channel = $this->params['channel'];
        Business_Feed_VbaseModel::$opts = $this->params;
        $this->pull_direction = !empty($this->params['pullDirection'])?$this->params['pullDirection']:'down';
        $this->pull_times = !empty($this->params['pullTimes'])?$this->params['pullTimes']:1;
        $this->Service_Feed_FeedModel = new Service_Feed_FeedModel();
        $this->replacedFlag = $this->params['replacedFlag'];

        if($this->channel=='news_tuijian'){
            $this->tuijian_offset = $this->params['offset'];
            $this->tuijian_length = $this->params['length'];
        }elseif($this->channel=='news_finance'){
            $this->finance_down_enable = false;
        }elseif($this->channel=='news_video'){
            $this->video_down_enable = true;
            //编辑维护的数据需要取tags接口
            if(($this->pull_direction=='down'&&$this->pull_times==1) || $this->pull_direction=='up'){
                Business_Feed_VbaseModel::$getVideoTags = true;
            }
        }elseif($this->channel=='news_ent'){
            $this->ent_down_enable = false;
        }

        //支持下拉的增加通用参数标识
        if($this->channel=='news_news' || $this->finance_down_enable || $this->video_down_enable || $this->ent_down_enable){
            $this->downEnable = $this->params['downEnable'] = true;
        }else{
            $this->downEnable = $this->params['downEnable'] = false;
        }

        //计算page时要依赖是否支持下拉
        $this->page = $this->params['page'] = $this->getPage();
        if($this->page==1 && $this->pull_direction=='down') {
            $this->params['pageSize'] = $this->pageSize = 20;
        }else{
            $this->params['pageSize'] = $this->pageSize = 10;
        }

        //缓存开关
        if($common_config['data'][0]['tuijiancache']=='no'){
            $this->tuijianCache = false;
        }
        if($common_config['data'][0]['allfeedcache']=='yes'){
            $this->allFeedCache = true;
        }

        //feed整个缓存
        if($this->allFeedCache){
            $allFeedListMcacheKey = 'newsapp_channel_list_allfeedlist_'.$this->params['platform'].'_'.$this->params['version'].'_'.$this->pull_direction.'_'.$this->channel.'_'.$this->page.'_'.$this->pageSize.'_'.$this->tuijian_offset.'_'.$this->tuijian_length;
            $data = Mcache::getInstance()->get($allFeedListMcacheKey);
            if(!empty($data)){
                Tools::outPut(0, $data, 'die');
            }
        }

        if($this->channel=='news_tuijian' || $this->channel=='news_video' || $this->finance_down_enable || $this->ent_down_enable){
            $data['isIntro'] = 0;//长标无摘要
        }else{
            $data['isIntro'] = 1;//短标有摘要
        }
        if($version>=550){
            $data['isIntro'] = 0;//长标无摘要
        }
        if($this->channel=='news_pic'){
            $data['isIntro'] = 1;
        }

        //550新加字段 下拉后内容展现方式 叠加|替换 add|replace
        $data['feedDownType'] = 'replace';
        //视频频道叠加
        if($this->video_down_enable){
            $data['feedDownType'] = 'add';
        }

        $data['feedLastIndex'] = $this->feedLastIndex;
        $data['lastTimestamp'] = (string)time();
        //非推荐频道，只有下拉和默认第一页时输出焦点图和广告
        if($this->pull_direction=='down' && $this->channel!='news_tuijian') {
            //头条 财经下拉特殊标识
            if($this->downEnable && $this->page>1){
                $this->pull_down_recommend=true;
            }
            $need_focus = Service_Feed_FeedModel::needFocus($this->channel,$this->params);
            if($need_focus) {
                //取焦点图
                //focusCache
                $focusMcacheKey = 'newsapp_channel_focuslist_'.$this->channel.'_'.$this->page.'_'.$this->pageSize;
                //local城市的
                if(!empty($this->params['originalChannel'])){
                    $focusMcacheKey .= '_'.$this->params['originalChannel'];
                }
                $focusData = Mcache::getInstance()->get($focusMcacheKey);
                if(empty($focusData)) {
                    $focusData = $this->getFocusList();
                    if(!empty($focusData)){
                        Mcache::getInstance()->set($focusMcacheKey, $focusData, 120);
                    }
                }
                if (!empty($focusData)) {
                    //取焦点图广告信息
                    $focusAdData = $this->Service_Feed_FeedModel->getChannelAdList($this->params, 'focus');
                    //将焦点图广告插入
                    $focusData = $this->insertAdData($focusData, $focusAdData);

                    $data['focus'] = $focusData;
                    $this->hasFocus = true;
                }
            }

            //取feed广告
            $feedAdData = $this->Service_Feed_FeedModel->getChannelAdList($this->params,'feed');
        }

        //取feed流
        //feedCache
        $feedMcacheKey = 'newsapp_channel_feedlist_'.$this->params['platform'].'_'.$this->params['version'].'_'.$this->pull_direction.'_'.$this->channel.'_'.$this->page.'_'.$this->pageSize;
        //local城市的
        if(!empty($this->params['originalChannel'])){
            $feedMcacheKey .= '_'.$this->params['originalChannel'];
        }
        //推荐频道和下拉推荐的不取缓存
        if($this->channel!='news_tuijian' && !$this->pull_down_recommend) {
            $feedData = Mcache::getInstance()->get($feedMcacheKey);
        }
        if(empty($feedData)){
            $feedData = $this->getArticleList();
            //直播频道根据状态进行重新排序
            if($this->channel=='news_live'){
                $feedData = Business_Feed_OperatingModel::news_live_feed_order($feedData);
            }
            if(!empty($feedData) && $this->channel!='news_tuijian' && !$this->pull_down_recommend){
                Mcache::getInstance()->set($feedMcacheKey, $feedData, 120);
            }
        }

        $data['feed'] = $feedData;

        //红包飞活动，给文章加分数 2017年1月5日15:54:16 by shaonan1
        /*if($version>=600 and $this->params['channel'] == 'news_live') {
            $formatedFeedData = Service_Activity_ActivityModel::addScore($data['feed']);
            if (!empty($formatedFeedData)) {
                $data['feed'] = $formatedFeedData;
            }
        }*/

        if (!empty($feedAdData) && count($feedData) > 5) {
            $data['ad']['feed'] = $feedAdData;
        }

        //头条插入固定新闻
        if($this->channel == 'news_news' && $this->pull_direction=='down'){
            $data['feed'] = Business_Feed_OperatingModel::InsertFixedNews($data['feed'],$this->params);
        }elseif($this->channel=='news_gread' && $this->pull_direction=='down' && $this->page==1 && $this->hasFocus==false){
            //精读频道特殊 news_gread 没有焦点图时，取第一张为焦点图
            $data['focus'][] = $data['feed'][0];
            array_shift($data['feed']);
        }elseif ($this->channel == 'news_auto' && $this->pull_direction=='down' && $this->page>=1){ 	//汽车频道增加入口
		     //汽车频道入口及秒车相关内容下线 2016.5.31  by ligen wanglei
            //$data['feed'] = Business_Feed_OperatingModel::InsertMiaocheCard($data['feed'], $this->params);
            if($this->params['versionNum']<=531){
                $data['entry']['isShow'] = 0;
            }else {
                $auto_data = Business_Feed_OperatingModel::getAutoInnoway();
                if (!empty($auto_data)) {
                    $data['autoEntry'] = $auto_data;
                }
            }
        }elseif($this->channel == 'news_sports' && $version>530 && $this->pull_direction=='down'){
            $h5entry_data = Business_Feed_OperatingModel::sportsEntryData();
            if(!empty($h5entry_data)) {
                $data['h5entry'] = $h5entry_data;
            }
        }/*elseif($this->channel == 'news_2016aoyun' && $this->pull_direction=='down' && $this->page>=1){
            $h5entry_data = Business_Feed_OperatingModel::aoYunH5Entry2016();
            if(!empty($h5entry_data)) {
                $data['h5entry'] = $h5entry_data;
            }
        }*/elseif($this->channel=='news_live' && $this->pull_direction=='down'){
            $data['liveForecastNums'] = intval(Service_Bn_BnModel::getliveForecastList('num'));
            //600增加二级分类
            if($version>=600){
                $col_entry = Business_Feed_OperatingModel::newws_live_col_entry();
                if(!empty($col_entry)){
                    $data['colEntry'] = $col_entry;
                }
            }
            //610增加热门直播，feed中插入一直播
            if(($this->params['platform']=='iphone'&&$version>=610)||($this->params['platform']=='android'&&$version>=611)){
                $news_live_hotlist = Business_Feed_OperatingModel::news_live_hotlist();
                if(!empty($news_live_hotlist)){
                    $data['hotList'] = $news_live_hotlist;
                }
                $data['feed'] = Business_Feed_OperatingModel::news_live_feed_insert_yizhibo($data['feed']);
            }

        }elseif(strpos($this->channel,'local_')!==false && $this->pull_direction=='down' && $version>=540){//本地频道
            //由于列表里可能出现天气预警新闻。所以要先取天气状况，再出feed列表。
            $weather_info = Service_Local_WeatherModel::getWeatherInfo($this->params,'feed');
            if(!empty($weather_info)){
                $data['weatherInfo'] = $weather_info;
            }
            //从个性化取城市新闻，有天气预警的加入预警信息
            $data['feed'] = Service_Local_LocalModel::getLocalCityNews($data['feed'],$this->params);
        }elseif(strpos($this->channel,'house_')!==false && $this->pull_direction=='down' && $version>550){//房产频道
            $h5entry_data = Business_Feed_OperatingModel::HouseEntryData();
            if(!empty($h5entry_data)) {
                $data['h5entry'] = $h5entry_data;
            }
        } elseif (strpos($this->channel,'mp_video_') !== false) {
            //视频自媒体人信息
            $mpId = str_replace('mp_video_','',$this->channel);
            $weiboInfo = Service_Weibo_WeiboModel::Users_show(array('uid' => $mpId));
            $weiboInfo = $weiboInfo['data'];
            if (!empty($weiboInfo['name']) && !empty($weiboInfo['avatar_large']) && !empty($weiboInfo['description'])) {
                $data['mpVideoInfo'] = array(
                    'name' => $weiboInfo['name'],
                    'pic' => $weiboInfo['avatar_large'],
                    'description' => $weiboInfo['description'],
                    'channelId' => $this->channel
                );
            }

        }
        //头条加入奥运新闻
        /*if($this->channel == 'news_news' && $version>=530 && $this->pull_direction=='down' && $this->page>=1){
            $olympicNews_data =  Business_Feed_OperatingModel::ToutiaoAoYunNews();
            if(!empty($olympicNews_data)) {
                $data['olympicNews'] = $olympicNews_data;
            }
        }*/

        //上拉分页广告的数据 511版本后增加
        if ($version>=511 && $this->pull_direction == 'up') {

            $feedAdData = $this->Service_Feed_FeedModel->getChannelAdList($this->params, 'feed');
            if (count($feedAdData) >= 5) {

                if ($version >= 550) {

                    //更改广告数组的键值
                    foreach ($feedAdData as $adKey => $adData) {
                        unset($feedAdData[$adKey]);
                        $feedAdData[($adKey+1)*5] = $adData;
                    }
                    $data['feed'] = $this->insertAdData($data['feed'],$feedAdData);
                } else {

                    $data['ad']['feed'] = $feedAdData;
                }
            }
        }

        //loading广告
        if($this->pull_direction=='down') {
            $loading_ad_data = Service_Ad_AdModel::GetLoadingAd($this->params);
            if ($loading_ad_data) {
                //没有新闻就没有广告
                $data['loadingAd'] = !empty($data['feed']) ? $loading_ad_data : array();
            }
        }

        //下拉返回提示文案
        if($this->pull_direction=='down' && $this->page>=1 && ($this->downEnable || $this->channel=='news_tuijian')){
            $data['downText'] = '有#n#条新内容';
        }

        //加入统计参数
        if(!empty($data['focus'])){
            $data['focus'] = Business_Feed_OperatingModel::AddStatisticsParams( $data['focus'],$this->params);
        }
        if(!empty($data['feed'])){
            $data['feed'] = Business_Feed_OperatingModel::AddStatisticsParams( $data['feed'],$this->params);
        }

        //数据修正
        $data['feed'] = Business_Feed_OperatingModel::DataRevise($data['feed'],$this->params);

        $status_code = 0;
        //只新闻类验证。 news_live新建频道数据不够，先不验证
        if(strpos($this->channel,'news_')!==false && $this->pull_direction == 'up' && $this->page<50 && empty($data['feed'])){
            $status_code = -1;
            $data = '';
        }


        if($this->downEnable && $this->pull_direction == 'down' && count($data['feed'])<4){
            $status_code = -1;
            $data = '';
        }

        if($this->allFeedCache && !empty($data['feed'])){
            Mcache::getInstance()->set($allFeedListMcacheKey, $data, 120);
        }

        Tools::outPut($status_code, $data, 'die');
    }

    /*
     * 获取文章列表
     */
    public function getArticleList()
    {
        Business_Feed_VbaseModel::$isFocus = $this->isFocus = false;
        $resource = Service_Feed_FeedModel::chooseResource($this->channel);
        switch($resource) {
            case 'comos' :
                $feedData = $this->getComosArticleList();break;
            case 'tuijian' :
                $feedData = $this->getTuijianArticleList();break;
            case 'house' :
                $feedData = $this->getHouseArticleList(); break;
            case 'mediaplatform' :
                $feedData = $this->getMediaPlatformArticleList(); break;
            case 'zhuanlan' :
                $feedData = $this->getZhuanlanArticleList(); break;
            case 'home':
                $feedData = $this->getHomeArticleList(); break;
            default:
                $feedData = array();
        }
        return $feedData;
    }

    public function getComosArticleList()
    {
        if($this->pull_down_recommend){
           if($this->channel=='news_news' || $this->channel=='news_finance' || $this->channel=='news_video') {
                if ($this->tuijianCache) {
                    $toutiaoTuijianMcacheKey = 'newsapp_channel_feedlist_tuijian_' . $this->channel . '_' . $this->page . '_' . $this->pageSize;
                    $feedData = Mcache::getInstance()->get($toutiaoTuijianMcacheKey);
                    if (empty($feedData)) {
                        $feedData = $this->Service_Feed_FeedModel->getRecommendList(($this->page - 2) * $this->pageSize, $this->pageSize, $this->params);
                        if (!empty($feedData)) {
                            Mcache::getInstance()->set($toutiaoTuijianMcacheKey, $feedData, 120);
                        }
                    }
                } else {
                    $feedData = $this->Service_Feed_FeedModel->getRecommendList(($this->page - 2) * $this->pageSize, $this->pageSize, $this->params);
                }
           }elseif($this->channel=='news_ent'){
               $feedData = $this->Service_Feed_FeedModel->getDownListFromComos($this->page-1, $this->pageSize, $this->params);
           }
            //没取到个性化数据的取第一屏缓存数据随机或小于4条数据的
            if(empty($feedData) || count($feedData)<4){
                //newsapp_channel_feedlist_android_v520_down_news_news_1_20
                $feedMcacheKey_down_1 = 'newsapp_channel_feedlist_'.$this->params['platform'].'_'.$this->params['version'].'_down_'.$this->channel.'_1_20';
                $feedData = Mcache::getInstance()->get($feedMcacheKey_down_1);
                if(!empty($feedData) && count($feedData)>9){
                    $feedData = array_slice($feedData,6);
                    shuffle($feedData);
                    $feedData = array_slice($feedData,0,9);
                    Header("X-feedType: cache");
                    Header("X-feedTime: ".time());
                }else{
                    //无缓存的取一次
                    $feedData = $this->Service_Feed_FeedModel->getChannelArticleList($this->params['channel'], 1, 20);
                    $feedData = $feedData['data'];
                    $feedData = array_slice($feedData,6);
                    shuffle($feedData);
                    $feedData = array_slice($feedData,0,9);
                    $feedData = Business_Feed_VbaseModel::Process($feedData);
                    Header("X-feedType: comossf");
                    Header("X-feedTime: ".time());
                }
            }
        } else {
            $feedData = $this->Service_Feed_FeedModel->getChannelArticleList($this->params['channel'], $this->page, $this->pageSize,$this->params);
            $this->feedLastIndex = $feedData['feedLastIndex'];
            $feedData = $feedData['data'];
            /*
            if($_SERVER['SERVER_NAME']== 'newsapi.dev.sina.cn' || $_SERVER['SERVER_NAME']== 'test.newsapi.sina.cn') {
                if ($this->channel == 'news_live') {
                    $yizhibo_live_data = Curlm::Get('http://interface.sina.cn/video/wap/get_miaopai_hot_live.d.json',array(),3000,300);
                    $yizhibo_live_data = json_decode($yizhibo_live_data,true);
                    $yizhibo_data = !empty($yizhibo_live_data['result']['data']['items'])?$yizhibo_live_data['result']['data']['items']:array();
                    $feed_temp_arr = array();
                    foreach($yizhibo_data as $yzbkey=>$yzbval)
                    {
                        if($yzbkey>20) break;
                        $feed_temp = array();
                        $feed_temp['longTitle'] = $yzbval['title'];
                        $feed_temp['link'] = $yzbval['url'];
                        $feed_temp['title'] = $yzbval['stitle'];
                        $feed_temp['intro'] = $yzbval['summary'];
                        $feed_temp['docTime'] =$yzbval['cTime'];
                        $feed_temp['pic'] = '';
                        $feed_temp['bpic'] = $yzbval['mainPic'];
                        $feed_temp['commentId'] = '';
                        $feed_temp['pubDate'] = strtotime($yzbval['cTime']);
                        $feed_temp['articlePubDate'] = '';
                        $feed_temp['mediaTypes'] = '';
                        $feed_temp_arr[] = $feed_temp;
                    }
                    $feedData = array_merge($feed_temp_arr, $feedData);

                }
            }
            */
            $feedData = Business_Feed_VbaseModel::Process($feedData);
        }

        return $feedData;
    }

    public function getTuijianArticleList()
    {
            if($this->channel=='news_tuijian') {
                $offset = $this->tuijian_offset;
                $length = $this->tuijian_length;
                if ($this->tuijianCache) {
                    $tuijianMcacheKey = 'newsapp_channel_feedlist_tuijian_news_tuijian_' . $offset . '_' . $length;
                    $feedData = Mcache::getInstance()->get($tuijianMcacheKey);
                    if (empty($feedData)) {
                        $feedData = Business_Feed_RecommendModel::getRecommendListByChannel($this->channel, $offset, $length, $this->params);
                        if (!empty($feedData)) {
                            Mcache::getInstance()->set($tuijianMcacheKey, $feedData, 120);
                        }
                    }
                } else {
                    $feedData = Business_Feed_RecommendModel::getRecommendListByChannel($this->channel, $offset, $length, $this->params);
                }
            }
            return $feedData;
    }

    public function getHomeArticleList($type='feed')
    {
        $page = $this->page;
        if($page>1 && $this->pageSize==10){
            $page += 1;
        }
        //house缓存
        $house_list_cachekey = 'NEWSAPP_HOUSE_LIST_DATA_'.$this->channel.'_'.$page.'_'.$this->pageSize;
        $feedData = Mcache::getInstance()->get($house_list_cachekey);
        if(empty($feedData)) {
            $feedData = Service_House_HouseModel::GetHomeArticleList($this->channel, $page, $this->pageSize, $this->params);
            if(!empty($feedData)){
                Mcache::getInstance()->set($house_list_cachekey, $feedData, 120);
            }
        }
        $focusLists = array();
        foreach($feedData as $key=>$data)
        {
            if(isset($data['isFocus']) && $data['isFocus']==true){
                unset($data['isFocus']);
                $focusLists[]=$data;
                unset($feedData[$key]);
            }
        }
        $feedData=array_values($feedData);

        if($type=='focus'){
            return $focusLists;
        }else{
            return $feedData;
        }
    }

    public function getHouseArticleList($type='feed')
    {
        $page = $this->page;
        if($page>1 && $this->pageSize==10){
            $page += 1;
        }
        //house缓存
        $house_list_cachekey = 'NEWSAPP_HOUSE_LIST_DATA_'.$this->channel.'_'.$page.'_'.$this->pageSize.'_'.$type;
        $feedData = Mcache::getInstance()->get($house_list_cachekey);
        if(empty($feedData)) {
            $feedData = Service_House_HouseModel::GetArticleList($this->channel, $page, $this->pageSize, $this->params,$type);
            if(!empty($feedData)){
                Mcache::getInstance()->set($house_list_cachekey, $feedData, 120);
            }
        }

        return $feedData;
    }

    public function getMediaPlatformArticleList()
    {
        //视频自媒体
        if(strpos($this->channel,'mp_video_')!==false){
            $page = $this->page;
            $feedData = Service_MediaPlatform_MediaPlatformModel::getMpVideoList($page, 10, $this->channel, $this->params);
        }else{//普通自媒体
            $channels = explode('_', $this->channel);
            $type = $channels[0];
            $column = $channels[1];
            $page = $this->page;
            if ($page > 1 && $this->pageSize == 10) {
                $page += 1;
            }
            $feedData = Service_MediaPlatform_MediaPlatformModel::GetArticleList($page, $this->pageSize, $column, $this->params);
        }
        return $feedData;
    }

    public function getZhuanlanArticleList()
    {
        $page = $this->page;
        if($page>1 && $this->pageSize==10){
            $page += 1;
        }
        $feedData = Business_Feed_ZhuanlanModel::GetRecommend($page, $this->pageSize, $this->params);
        return $feedData;
    }

    /*
     * 获取焦点图
     */
    public function getFocusList()
    {
        Business_Feed_VbaseModel::$isFocus = $this->isFocus = true;
        $resource = Service_Feed_FeedModel::chooseResource($this->channel);
        if($resource=='house'){
            $focusData = $this->getHouseArticleList('focus');
        }elseif($resource == 'home') {
            $focusData = $this->getHomeArticleList('focus');
        }else {
            $focusData = $this->Service_Feed_FeedModel->getChannelFocusList($this->channel, $this->page, $this->focusPageSize,$this->params);
            $focusData = !empty($focusData['data']) ? Business_Feed_VbaseModel::Process($focusData['data']) : array();
        }
        return $focusData;
    }

    /*
     * 子类中重写
     */
    public function getPage()
    {
        if($this->pull_direction=='up'){
            if($this->downEnable) {
                if ($this->replacedFlag == 0) {
                    $page = $this->pull_times + 1;
                } else {
                    $page = $this->pull_times;
                }
            }else{
                $page = $this->pull_times + 1;
            }
        }else{
            //非头条时下拉都为1
            if($this->downEnable){
                $page = $this->pull_times;
            }else{
                $page=1;
            }

        }



        /*
        $channel = $this->channel;
        //$client_platform = $this->params['client_platform'];
        $client_platform = 'android';
        $pull_direction = $this->params['pull_direction'];
        $pull_times = $this->params['pull_times'];
        $page = $this->params['page'] ? $this->params['page'] : 1;
        //头条频道有feed下拉功能
        if($channel == 'news_news')
        {
            if($pull_direction == 'down')
            {//下拉动作
                $pull_down_refresh = 1;//下拉刷新标志符置位
                if($client_platform == 'android')
                {
                    $psize = 20;
                    $opt['psize'] = 20;
                    if($pull_times == 1)
                    {//系统启动自动加载第一页 算作下拉刷新
                        $page = 1;
                    }
                    else
                    {
                        //$fixed_news_number = SinagoList::GetFixNewsNum($opt);
                        //17=20-3，3为目前第一页的广告数量
                        //$psize = 17 - $fixed_news_number;//20-(第一页的广告,目前是有3条) - 再减去一条固定新闻
                        $page = $pull_times;
                        //$offset = ($page - 2) * $psize ;
                        //$length = $psize;
                        $this->pull_down_recommend = true;
                    }
                }
                elseif($client_platform == 'iphone')
                {
                    $page = ($pull_times == 1 || $pull_times == 2) ? 1 : (($pull_times - 1) * 2 + 1);
                }
            }
            elseif($pull_direction == 'up')
            {//上拉动作
                $pull_up_refresh = 1;//上拉刷新标志符置位
                if($client_platform == 'iphone')
                {
                    $page = $pull_times <= 3  ? $pull_times + 1 : (($pull_times - 1) * 2);
                }
                elseif($client_platform == 'android')
                {//按照产品的规划，iphone用户和android用户执行不同的刷新策略
                    $page = $replaced_flag ? $pull_times : $pull_times + 1;
                }
            }
        }
        elseif ($channel == 'hdpic_hdpic' || $channel == 'video_smile_cry')
        { //图片、视频 频道
            if($pull_direction == 'down'){
                $pull_down_refresh = 1;//下拉刷新标志符置位
                if ($client_platform == 'android') {
                    $page = ($pull_times == 1) ? 1 : ($pull_times * 2 + 1);
                }else{
                    $page = ($pull_times == 1 || $pull_times == 2) ? 1 : (($pull_times - 1) * 2 + 1);
                }
            }elseif($pull_direction == 'up') {
                $pull_up_refresh = 1;//上拉刷新标志符置位
                $page = $pull_times <= 3  ? $pull_times + 1 : (($pull_times - 1) * 2);
            }
        }
        */
        return $page;
    }

    public function insertAdData($list, $addata)
    {
        if(!empty($addata) && is_array($addata)){
            foreach($addata as $key => $val)
            {
                array_splice($list, $key-1, 0, array($val));
            }
        }
        return $list;
    }


}