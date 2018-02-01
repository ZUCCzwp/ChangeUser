<?php

namespace Home\Controller;
use Org\Util\Tree;
use Org\Util\DataRbac;
use Home\Model\UserModel;
use Home\Model\StoreModel;

/**
 *    用户管理控制器
 *
 *    Y   2017-06-15
 *
**/
class UserController extends CommonController {
	//  用户列表
    public function usersList ()
    {
        // 超管可查看平台所有会员 不需要处理
        if(!session('superadmin'))
        {
            $map['u.business_id'] = UserModel::getLotPond();
            // dump($map);die;
        }
        // if($_SERVER['REMOTE_ADDR'] == '112.17.88.200'){
        //     dump($map);die;
        // }
        $searchKeyword = trim(I('searchKeyword',''));
        $type          = trim(I('type',''));
        $ab_id         = trim(I('ab_id',''));

        if ($searchKeyword && empty($type)) {
            $map['ui.user_name|u.phone'] = array($searchKeyword, $searchKeyword, '_multi'=>true);
        }elseif(empty($searchKeyword) && $type) {
            $map['is_vest'] = $type;
        }elseif ($searchKeyword && $type) {
            $map['is_vest']      = $type;
            $map['ui.user_name'] = $searchKeyword;
        }

        if ($ab_id) {
            $map['ab.id'] = $ab_id;
        }

        $User = M('User');

        $join  = 'left join tp_agent_busi ab ON u.business_id = ab.id';
        $join1 = 'left join tp_user_info ui ON u.id = ui.user_id';
        $join2 = 'left join tp_user_account ua ON ui.id = ua.user_id';

        // 实例化分页类 设置分页条数 默认为10 zhuyi
        $countRes = $User -> alias('u')
                          -> join($join)
                          -> join($join1)
                          -> join($join2)
                          -> where($map)
                          -> count();

        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyword);

        $field = ['ui.id','u.phone','u.is_vest', 'u.type', 'ab.agent_name', 'ui.user_name', 'ui.register_time', 'ui.last_login_time', 'ua.charged_money', 'ua.use_money + ua.cur_bonus my_money'];

        $userDatas = $User -> alias('u')
                           -> join($join)
                           -> join($join1)
                           -> join($join2)
                           -> field($field)
                           -> where($map)
                           -> order(['u.id DESC'])
                           -> limit($page -> firstRow, $page -> listRows)
                           -> select();
        foreach ($userDatas as &$v) {
            if($res = M('User_and_user')->field('pass,from_department')->getByUser_id($v['id'])){
                if($res['from_department'] == '5'){
                   //渠道部门id
                    $v['pass'] = array();
                    $pid = explode('-', $res['pass']);
                    foreach ($pid as $v2) {
                        if($v2 == '0'){
                            $temp['name'] = '代理';
                            
                        }else{
                            $r = M('User_info')->field('user_name')->getById($v2);
                            $temp['name'] = $r['user_name'];
                        }
                        $temp['pid'] = $v2;
                        array_push($v['pass'], $temp);
                    }
                    $temp['pid'] = $v['id'];
                    $temp['name'] = $v['user_name'];
                    array_push($v['pass'], $temp);
                }

                
            }
            
        }
       

                           
        $this->assign('userDatas',$userDatas);
        $this->assign('searchKeyword',$searchKeyword);
        $this->assign('type',$type);
               $this->assign('page',$page->show());

        $this -> assign('user_id', $user_id);
        $this->display('usersList');

    }

    // 获取用户更详细信息
    public function moreinfo()
    {
        $user_id = trim(I('user_id'));

        // 彩民基本信息
        $user_info_model = M('user_info');

        $userInfo = $user_info_model -> alias('ui')
                                     -> join('LEFT JOIN tp_user u ON ui.user_id = u.id')
                                     -> join('LEFT JOIN tp_user_account ua ON ui.id = ua.user_id')
                                     -> where(['ui.id' => $user_id])
                                     -> field('ui.id, ui.user_name, ui.user_id, ui.real_name, ui.card_code, ui.register_time, ui.last_login_time, u.phone, u.store_id, u.is_vest, u.business_id, ua.use_money, ua.trade_money, ua.charged_money, ua.freez_money, ua.cur_bonus, ua.bonus, ua.use_money + ua.cur_bonus money')
                                     -> find();


        $this -> assign('userInfo', $userInfo);

        /*

        // 彩民资金信息
        $user_account_model = M('user_account');
        $userAccount = $user_account_model -> where(['user_id' => $user_id]) -> field('trade_money, charged_money, bonus') -> find();

        $this -> assign('userAccount', $userAccount);

        // 彩民财务状况
        $userMoney = $user_account_model -> where(['user_id' => $user_id]) -> field('freez_money, use_money, charged_money + bonus money') -> find();

        $this -> assign('userMoney', $userMoney);

        */

        // 彩民购彩信息
        $user_order_model = M('user_order');
        $user_buy_info_model = M('user_buy_info');
        $user_rebate = M("user_rebate");
        $user_money_model = M('user_money');//佣金表
        $userOrder = $user_order_model -> where(['user_id' => $user_id]) -> order('id ASC') -> field('amount_money, win_status') -> find();
        $this -> assign('userOrder', $userOrder);

        // 彩民中奖比例
        $ordersmoney = $user_buy_info_model -> field("sum(buy_money) as tz,sum(bonus+jiajiang) as zj")-> where(['uid' => $user_id,'ch_order_status'=>'1']) ->select();//总投足金额、中奖金额
        $wincomm = $user_money_model -> where(['user_id' => $user_id]) -> sum('money');//佣金
        if($wincomm==null){
            $wincomm =0;
        }
        if($ordersmoney){

            $userWinPro = sprintf("%.2f", ($ordersmoney[0]['tz'] / $ordersmoney[0]["zj"]+$wincomm)*100);
        }else{
            $userWinPro = sprintf("%.2f", 0);
        }
        $this -> assign('orders', $ordersmoney[0]['tz']);
        $this -> assign('userWinPro', $userWinPro);

        // 彩民支付账户信息
        $card_model = M('card');
        $userCard = $card_model -> alias('c')
                                -> join('LEFT JOIN tp_bank b ON b.id = c.bank_id')
                                -> field('c.id, c.bank_id, c.card_no, c.card_name, c.phone, c.open_bank, b.name')
                                -> where(['user_id' => $user_id, 'c.status' => '1', 'c.is_def' => '1'])
                                -> select();

        $this -> assign('userCard', $userCard);


        // 彩民归属
        $userStore = $user_info_model -> alias('ui')
                                 -> join('LEFT JOIN tp_user u ON u.id = ui.user_id')
                                 -> join('LEFT JOIN tp_store s ON u.store_id = s.id')
                                 -> join('LEFT JOIN tp_agent_busi ab ON u.business_id = ab.id')
                                 -> join('LEFT JOIN tp_role r ON r.id = ab.idtn')
                                 -> field('s.id sid, s.store_name, ab.agent_name, ab.idtn, r.sector_id, r.name')
                                 -> where(['ui.id' => $user_id])
                                 -> find();

        $this -> assign('userStore', $userStore);

        $userRe  =   $user_rebate ->alias('r')
                                  -> join('LEFT JOIN  `tp_user_and_user` ua ON r.user_id = ua.user_id')
                                  -> field('r . * , SUM( r.rebate_a + r.rebate_b + r.rebate_c + r.rebate_d + r.rebate_e + r.rebate_f + r.rebate_g + r.rebate_h ) rebate')
                                  -> where(['r.user_id' => $user_id])
                                  -> find();
        if($userRe['rebate']=='0.0'){
            $userRe = 0;
        }
        $this -> assign('userRe', $userRe);
        
        $user_store_model = M('user_store');
        $map = array();
        $map['us.store_id'] = $userStore['sid'];

        if ($userStore['sector_id'] == '4') {

            if ($userStore['idtn'] == '3') {
                $map['ab.idtn'] = array('in', '2, 5');
            }
            elseif ($userStore['idtn'] == '2')
            {
                $map['ab.idtn'] = '5';
            }

        }
        elseif ($userStore['sector_id'] == '5')
        {
            if ($userStore['idtn'] == '12') {
                $map['ab.idtn'] = array('in', '11, 10');
            }
            elseif ($userStore['idtn'] == '11')
            {
                $map['ab.idtn'] = '10';
            }

        }

        $userThe = $user_store_model -> alias('us')
                                     -> join('LEFT JOIN tp_agent_busi ab ON us.user_id = ab.id')
                                     -> join('LEFT JOIN tp_role r ON r.id = ab.idtn')
                                     -> field('ab.agent_name, r.name')
                                     -> where($map)
                                     -> select();
        $this -> assign('userThe', $userThe);

        $this -> assign('user_id', $user_id);

        $this -> display();
    }


    /**
     *  获取用户信息
     *  
     *   Y 2017-09-19
     */
    public function getUserInfo()
    {
        $user_id = trim(I('user_id'));

//        dd($user_id);

        $user_buy_info_model = M('user_buy_info');

        $orderInfo = $user_buy_info_model -> alias('ub')
                                          -> join('LEFT JOIN tp_user_order uo ON ub.order_id = uo.id')
                                          -> join('LEFT JOIN tp_user_info ui ON ui.id = uo.user_id')
                                          -> join('LEFT JOIN tp_user u ON ui.user_id = u.id')
                                          -> join('LEFT JOIN tp_store s ON u.store_id = s.id')
                                          -> field('uo.order_code, ub.ch_order_status order_status, ub.buy_num lot_multi , uo.pour_num, ub.buy_time add_time, uo.order_type, s.store_name')
                                          -> where(['uo.user_id' => $user_id])
                                          -> order('ub.id desc')
                                          -> select();
        $result = array();
        foreach ($orderInfo as $key => $value) {

            $result[$key] = $value;

            if ($value['order_status'] == '0') {
                $result[$key]['order_status'] = '未支付';
            }elseif($value['order_status'] == '1'){
                $result[$key]['order_status'] = '已支付';
            }elseif($value['order_status'] == '2'){
                $result[$key]['order_status'] = '失效';
            }

            if ($value['order_type'] == '0') {
                $result[$key]['order_type'] = '普通代购';
            }elseif($value['order_type'] == '1'){
                $result[$key]['order_type'] = '复制跟单';
            }elseif($value['order_type'] == '2'){
                $result[$key]['order_type'] = '预约跟单';
            }elseif($value['order_type'] == '12'){
                $result[$key]['order_type'] = '合买';
            }elseif($value['order_type'] == '22'){
                $result[$key]['order_type'] = '追号';
            }

        }

        $res = array();
        $res['data'] = $result;


        return $this -> ajaxReturn($res);

    }

    /**
     *  获取彩民充值记录
     *
     *  Y 2017-09-20
     */
    public function getRef()
    {
        $user_id = trim(I('user_id'));

        $sale_banktmp_model = M('sale_banktmp');

        $userRef = $sale_banktmp_model -> alias('sbt')
                                    -> join('tp_base_useriotype but ON sbt.buit_id = but.uo_id')
                                    -> field('sbt.id, sbt.order_no, but.uo_note, sbt.recharge_money, sbt.service_money, sbt.create_time, sbt.pay_time, sbt.status')
                                    -> where(['sbt.user_id' => $user_id])
                                    -> order('sbt.pay_time ASC')
                                    -> select();

        $result = array();

        foreach ($userRef as $key => $value) {
            $result[$key] = $value;

            if ($value['status'] == '0') {
                $result[$key]['status'] = '待支付';
            }elseif($value['status'] == '1'){
                $result[$key]['status'] = '已支付';
            }elseif($value['status'] == '2'){
                $result[$key]['status'] = '交易结束';
            }elseif($value['status'] == '3'){
                $result[$key]['status'] = '未付款超时';
            }

            if (empty($value['pay_time'])) {
                $result[$key]['pay_time'] = $value['create_time'];
            }
        }

        $res['data'] = $result;

        $this -> ajaxReturn($res);
    }

    /**
     *  获取彩民提现记录
     *
     *  Y 2017-09-20
     */
    public function getCash()
    {
        $user_id = trim(I('user_id'));

        $userCash = M('sale_getmoney') -> field('cash_no, cash_type, cash_card, cash_money, case_service_money, cash_time, status')
                                                -> where(['user_id' => $user_id])
                                                -> order('cash_time')
                                                -> select();

        $result = array();

        foreach ($userCash as $key => $value) {
            $result[$key] = $value;

            if ($value['cash_type'] == '1') {
                $result[$key]['cash_type'] = '银行卡';
            }elseif ($value['cash_type'] == '2')
            {
                $result[$key]['cash_type'] = '支付宝';
            }

            if ($value['status'] == '0') {
                $result[$key]['status'] = '待处理';
            }elseif ($value['status'] == '1')
            {
                $result[$key]['status'] = '提款成功';
            }elseif ($value['status'] == '2')
            {
                $result[$key]['status'] = '拒绝';
            }
        }

        $res['data'] = $result;

        $this -> ajaxReturn($res);

    }

    //  获取标签信息
    public function getLabelDatas()
    {
        $labelInfo = S('md5(labelInfo)');
        if (empty($labelInfo)) {
            $labelInfo = UserModel::getLabel ();
            S('md5(labelInfo)', $labelInfo, 300);
        }

        $userLabel = M('user') -> where(['id' => I('id/d')]) -> getField('lab_id');

        $selectLabel = explode('_', $userLabel);

        $newArr = array();
        foreach ($labelInfo as $key => $value) {
            $newArr[$key] = $value;
            foreach ($selectLabel as $k => $v) {

                if ($value['id'] == $v) {
                    $newArr[$key]['idtn'] = '1';
                }
            }
        }

        $this -> assign('newArr', $newArr);

        echo $this -> fetch('add_label');

    }

    /**
     * [addLabelUser 获取所有的用户标签]
     */
    public function addLabelUser()
    {
        $result = UserModel::addLabelUser (I('post.'));

        if ($result) {
            $returnAjax['status'] = '1';
        }

        return $this -> ajaxReturn($returnAjax);
    }

    // 获取会员使用的设备
    private function getUserSourse($id)
    {
        $sourse   = ['安卓','苹果','PC'];

        $LoginLog = M('user');

        $type     = $LoginLog->where(['user_id' => $id])->getField('type');

        return $sourse[$type - 1];
    }

    // 获取会员类型 会员 马甲
    private function getUserType($is_vest = 0)
    {
        $userType = ['会员','马甲','内部'];

        return $userType[$is_vest];
    }

    // 查看用户信息
    public function userView()
    {
        if(!empty(I('get.is_getUserInfo'))){
            $uid = I('get.id',0);
            if($uid==0){
                return $this->error('用户不存在');
            }
            $labs = M('user_label')->where('user_id='.$uid)->find();
            $v    = M('user_buy_info')->where('uid='.$uid)->find();
            $userViewData = M('user_info') 
            -> alias('ui')
            -> join('LEFT JOIN tp_user u ON u.id = ui.user_id')
            -> join('LEFT JOIN tp_user_account ua ON ui.id = ua.user_id')
            -> field('u.phone,ua.user_id, ui.user_name, ua.use_money, ua.unbalance_money, ua.trade_money, ua.charged_money, ua.bonus, ua.freez_money, ua.cur_bonus, ua.use_money+ua.cur_bonus zMoney,ui.real_name,ui.card_code')
            -> where('ui.id='.$uid)
            -> find();
           
            $this->assign('userViewData',$userViewData);
            $this->assign('labs',$labs);
            $this->display('getUserInfo');
            exit;
        }
        $uid = I('id',0,'intval');
        $User = M('User');
        $user_buy_info_model = M('user_buy_info');

        $user_id = M('user_info') -> where(['id' => $uid]) -> getField('user_id');
        $isExist = $User->where(['id' => $user_id])->count('id');
        if(!$isExist)
        {
            $this -> error('当前用户不存在', U('user/usersList'));
        }

        $userData = $User->field(['id','phone','is_vest', 'lab_id','status'])->order(['id' => 'desc'])->find($user_id);

        $this -> labs = UserModel::editBle ($userData['lab_id']);

        $UserInfo    = M('UserInfo');
        $UserAccount = M('UserAccount');

        $userInfoData    = $UserInfo -> field(['id','user_name','register_time','last_login_time','real_name','card_code'])
                                     -> where(['user_id' => $userData['id']])
                                     -> order('id desc')
                                     -> find();

        $userAccountData = $UserAccount -> field(['use_money + cur_bonus my_money','cur_bonus','bonus','use_money','unbalance_money','trade_money','charged_money'])
                                        -> where(['user_id' => $userInfoData['id']])
                                        -> find();
         $ordersmoney = $user_buy_info_model -> field("sum(buy_money) as tz,sum(bonus+jiajiang) as zj, jiajiang")-> where(['uid' => $uid,'ch_order_status'=>'1']) ->select();//总投足金额、中奖金额
        $user_money_model = M('user_money');//佣金表
        $wincomm = $user_money_model -> where(['user_id' => $uid]) -> sum('money');//佣金
        $userViewData = [
                'user_id'         =>  $userData['id'],
                'phone'           =>  substr_replace($userData['phone'],'****',3,4),
                'status'          =>  $userData['status'],
                'user_name'       =>  $userInfoData['user_name'],
                'register_time'   =>  $userInfoData['register_time'],
                'last_login_time' =>  $userInfoData['last_login_time'],
                'my_money'        =>  $userAccountData['my_money'],
                'cur_bonus'       =>  $userAccountData['cur_bonus'],
                'use_money'       =>  $userAccountData['use_money'],
                'unbalance_money' =>  $userAccountData['unbalance_money'],
                'trade_money'     =>   $ordersmoney[0]["tz"],
                'charged_money'   =>  $userAccountData['charged_money'],
                'vest'            =>  $this->getUserType($userData['is_vest']),
                'bonus'           =>  $userAccountData['bonus'],
                'wincomm'         =>  $wincomm,
                'jiajiang'        =>  $ordersmoney[0]["jiajiang"],
                'sourse'          =>  $this->getUserSourse($userData['id']),
                'user_info_id'    =>  $userInfoData['id'],
                'real_name'       =>  $userInfoData['real_name'],
                'card_code'       =>  $userInfoData['card_code']

            ];
            // dump($userViewData);die;
        $this->assign('userViewData',$userViewData);
        $this->display();
    }

    /**
     * [tran 店员转让自己的彩民]
     * @return [type] [description]
     */
    public function tran ()
    {

        $t     = trim($_GET['t']);//操作转入标识
        $ab_id = trim($_GET['ab_id']);//转入店员的ID

        if ($t) {
            $cmId       = trim($_GET['cmId']);//彩民ID

            // 获取转入店员的店铺ID
            $store_id = M('user_store') -> where(['user_id' => $ab_id]) -> getField('store_id');

            // 获取彩民入库ID
            $info = M('user_info') -> alias('ui') -> join("LEFT JOIN tp_user u ON u.id = ui.user_id") -> where(['ui.id' => $cmId]) -> field('ui.user_id, u.phone') -> find();

            $data['business_id'] = $ab_id;
            $data['store_id']    = $store_id;

            $u       = array();
            $u['id'] = $info['user_id'];

            $result = M('user') -> where($u) -> data($data) -> save();

            if ($result) {

                $agInfo = M('business_group') -> where(['user_phone' => $info['phone']]) -> getField('id');

                if ($agInfo) {
                    $ag                = array();
                    $ag['business_id'] = $ab_id;
                    $ag['store_id']    = $store_id;
                    $ag['add_time']     = time();
                    $ag['remark']      = '由'. session('user_auth.agent_name') .'转入';

                    M('business_group') -> where(['id' => $agInfo]) -> data($ag) -> save();
                }

                $this -> success('转入成功', U('user/userslist'));
            }else{
                $this -> error('转入失败', U('user/userslist'));
            }

        }else{

            $map   = array();
            $where = array();
            $user_store_model = M('user_store');

            $cmId       = trim($_GET['id']);//彩民ID

            $selectType = trim($_GET['selectType']);
            $stores     = trim($_GET['stores']);
            $key        = trim($_GET['key']);

            if ($key) {
                    $map['agent_name'] = $key;
                }

                $store = M('user_store') -> where(['user_id' => session('user_auth.id')]) -> getField('store_id');

            if ($selectType != '1' ) {//不跨店
                // 获取到当前电源的店铺ID  ==  不跨店转让

                $map['store_id']   = $store;
                $map['idtn']       = '3';


            }else {//跨店

                $map['idtn'] = '3';

                if ($stores) {
                    $map['s.id'] = $stores;
                }

            }

            //统计搜索结果
            $countRes =  $user_store_model -> alias('us')
                                           -> join('LEFT JOIN tp_agent_busi ab ON us.user_id = ab.id')
                                           -> join('LEFT JOIN tp_store s ON us.store_id = s.id')
                                           -> where($map)
                                           -> count();

            // 实例化搜索类 设置分页条数 默认为10
            $page     = new \Think\Page($countRes, 10);
            // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
            $page -> parameter['key'] = urlencode($keyWord);

            $userStore = $user_store_model -> alias('us')
                                         -> join('LEFT JOIN tp_agent_busi ab ON us.user_id = ab.id')
                                         -> join('LEFT JOIN tp_store s ON us.store_id = s.id')
                                         -> where($map)
                                         -> field('user_id, store_id, agent_name, store_name')
                                         -> limit($page -> firstRow.','.$page -> listRows)
                                         -> select();

            // 获取该平台下，所有的店铺
            $where['store_id'] = $store;
            $where['idtn']     = '1';
            $where['s.status'] = array('neq', '44');
            $allStore = $user_store_model -> alias('us')
                                          -> join('LEFT JOIN tp_agent_busi ab ON us.user_id = ab.id')
                                          -> join('LEFT JOIN tp_store s ON s.busi_id = us.user_id')
                                          -> where($where)
                                          -> field('user_id, store_name, s.id')
                                          -> select();

            $caName = M('user_info') -> where(['id' => $cmId]) -> getField('user_name');

            $this -> assign('userStore', $userStore);
            $this -> assign('allStore', $allStore);
            $this -> assign('selectType', $selectType);
            $this -> assign('cmId', $cmId);
            $this -> assign('caName', $caName);
            $this -> assign('stores', $stores);
            $this -> assign('sid', session('user_auth.id'));
            $this -> assign('keyWord', $key);
            $this -> assign('page',$page->show());
            $this -> display();

        }
    }

    // 重置密码
    public function pwdReset()
    {
        $user_id = I('id','0','intval');

        $User    = M('User');

        $isExist = $User->where(['id' => $user_id])->getField('id');

        if(!$isExist)
        {
            die(json_encode(['status' => 0,'msg' => '当前用户不存在']));
        }

        $passwd   = $this -> getNickName(8);

        $userData = ['id' => $user_id, 'passwd' => md5(trim($passwd))];

        $result   = $User->save($userData);

        if($result)
        {
            die(json_encode(['status' => 1,'msg' => '重置密码成功<br />新密码为:'.$passwd]));
        }

        echo json_encode(['status' => 0,'msg' => '重置密码失败']);
    }


    public function updateCardcode()
    {
        $user_id  = I('id','0','intval');
        $cardcode = I('cardcode','','trim');

        $UserInfo = M('UserInfo');

        $user_info_id = $UserInfo -> where(['user_id' => $user_id])
                                  -> order(['id' => 'desc'])
                                  -> getField('id');

        if(!$user_info_id)
        {
            die(json_encode(['status' => 0,'msg' => '当前用户不存在']));
        }

        $find = $UserInfo -> where(['card_code'=>$cardcode])->find();
        if($find){
            die(json_encode(['status' => 0,'msg' => '当前身份证已在平台绑定!']));
        }

        $userInfoData      = ['card_code' => $cardcode];

        $resultUserInfo    = $UserInfo -> where(['user_id' => $user_id, 'id' => $user_info_id]) -> save($userInfoData);

        if($resultUserInfo)
        {
           
            die(json_encode(['status' => 1, 'msg' => '身份证号修改成功']));
        }

       
        die(json_encode(['status' => 0,'msg' => '身份证号修改失败']));
    }


    public function updateUserStatus()
    {
        $user_id = I('id','0','intval');
        $status  = I('status','','trim');

        $User = M('User');

        if(!$User->where(['id'=>$user_id])->find())
        {
            die(json_encode(['status' => 0,'msg' => '当前用户不存在']));
        }
        $save    = $User -> where(['id' => $user_id]) -> save(['status'=>$status]);

        if($save)
        {
           
            die(json_encode(['status' => 1, 'msg' => '用户状态修改成功']));
        }

       
        die(json_encode(['status' => 0,'msg' => '用户状态修改失败']));

    }

      public function updateRealname()
    {
        $user_id  = I('id','0','intval');
        $realname = I('realname','','trim');

        $UserInfo = M('UserInfo');

        $user_info_id = $UserInfo -> where(['user_id' => $user_id])
                                  -> order(['id' => 'desc'])
                                  -> getField('id');

        if(!$user_info_id)
        {
            die(json_encode(['status' => 0,'msg' => '当前用户不存在']));
        }

        

        $userInfoData      = ['real_name' => $realname];

        $resultUserInfo    = $UserInfo -> where(['user_id' => $user_id, 'id' => $user_info_id]) -> save($userInfoData);

        if($resultUserInfo)
        {
           
            die(json_encode(['status' => 1, 'msg' => '真实姓名修改成功']));
        }

       
        die(json_encode(['status' => 0,'msg' => '真实姓名修改失败']));
    }

    // 更新用户昵称
    public function updateNickname()
    {
        $user_id  = I('id','0','intval');
        $nickname = I('nickname','','trim');

        $UserInfo = M('UserInfo');

        $isExist  = $UserInfo -> field(['nickname'])
                              -> where(['user_name' => $nickname])
                              -> count();

        $isExist && die(json_encode(['status' => 0,'msg' => '当前昵称已存在']));

        $user_info_id = $UserInfo -> where(['user_id' => $user_id])
                                  -> order(['id' => 'desc'])
                                  -> getField('id');

        if(!$user_info_id)
        {
            die(json_encode(['status' => 0,'msg' => '当前用户不存在']));
        }

        $UserAccount = M('UserAccount');

        $UserInfo -> startTrans();

        $userInfoData      = ['user_name' => $nickname];

        $resultUserInfo    = $UserInfo -> where(['user_id' => $user_id, 'id' => $user_info_id]) -> save($userInfoData);

        $userAccountData   = ['user_name' => $nickname];

        $resultUserAccount = $UserAccount -> where(['user_id' => $user_info_id]) -> save($userInfoData);

        if($resultUserInfo)
        {
            $UserInfo->commit();
            die(json_encode(['status' => 1, 'msg' => '昵称修改成功']));
        }

        $UserInfo->rollback();
        die(json_encode(['status' => 0,'msg' => '昵称修改失败']));
    }

    // 用户充值
    public function recharge()
    {
        $user_id      = I('id','0','intval');
        $money        = I('toMoney','0','intval');

        $UserInfo     = M('UserInfo');

        $userInfoData = $UserInfo -> where(['user_id' => $user_id])
                                  -> field(['id','user_name'])
                                  -> order(['id' => 'desc'])
                                  -> find();

        if(!$userInfoData)
        {
            die(json_encode(['status' => 0,'msg' => '当前用户不存在']));
        }

        // 开启事务
        $UserInfo->startTrans();

        // 添加充值订单
        $order_no     = "CZ".date('YmdHis') . strval(mt_rand(100, 999));
        $rechargeData = [
                'id'       => $userInfoData['id'],
                'addmoney' => $money,
                'order_no' => $order_no
            ];

        $sale_banktmp_id = $this->createRechargeOrder($rechargeData);

        // 更新用户账户
        if($sale_banktmp_id)
        {
            $sUserAccountData = $this->updateUserAccount(['user_info_id' => $userInfoData['id'],'money' => $money]);
        }

        // 添加用户流水
        if($sUserAccountData)
        {
            $userPayLogData = [
                    'pay_make_id' => $sale_banktmp_id,
                    'user_id'     => $userInfoData['id'],
                    'user_name'   => $userInfoData['user_name'],
                    'order_id'    => $order_no,
                    'pay_money'   => $money,
                    'has_pay'     => $sUserAccountData['my_money']
                ];

            $sale_userpaylog_id = $this->createUserPayLog($userPayLogData);
        }

        if($sale_userpaylog_id)
        {
            // 事务提交
            $UserInfo->commit();
            die(json_encode(['status' => 1,'msg' => '充值成功:'.$money.'元','data' => $sUserAccountData]));
        }

        // 事务回滚
        $UserInfo->rollback();
        die(json_encode(['status' => 0,'msg' => '充值失败,请稍后重试']));
    }

    // 添加充值订单
    private function createRechargeOrder($rechargeData = array())
    {
        $user_id     = $rechargeData['id'];
        $addmoney    = $rechargeData['addmoney'];
        // $order_no = "CZ".date('YmdHis') . strval(mt_rand(100, 999));
        $order_no    = $rechargeData['order_no'];

        $saleBanktmpData = array(
                'user_id'        =>  $user_id,
                'buit_id'        =>  '23',
                'recharge_money' =>  $addmoney,
                'service_money'  =>  '0.00',
                'order_no'       =>  $order_no,
                'pay_time'       =>  date('Y-m-d H:i:s'),
                'status'         =>  '1'
            );

        $SaleBanktmp     = M('SaleBanktmp');

        $sale_banktmp_id = $SaleBanktmp->add($saleBanktmpData);

        return $sale_banktmp_id;
    }

    // 更新账户余额
    private function updateUserAccount($accountData = [])
    {
        $user_info_id    = $accountData['user_info_id'];
        $money           = $accountData['money'];

        $UserAccount     = M('UserAccount');

        $userAccountData = array(
                'use_money'     => array('exp','`use_money` + '.$money),
                'charged_money' => array('exp','`charged_money` + '.$money)
            );

        $resultUserAccount = $UserAccount->where(['user_id' => $user_info_id])->save($userAccountData);

        $sUserAccountData = false;

        if($resultUserAccount)
        {
            $sUserAccountData = $UserAccount -> field(['use_money + unbalance_money my_money','use_money','charged_money'])
                                             -> where(['user_id' => $user_info_id])
                                             ->find();
        }

        return $sUserAccountData;
    }

    // 添加用户流水
    private function createUserPayLog($payLogData = array())
    {
        $pay_make_id = $payLogData['pay_make_id'];
        $user_id     = $payLogData['user_id'];
        $user_name   = $payLogData['user_name'];
        $order_id    = $payLogData['order_id'];
        $pay_money   = $payLogData['pay_money'];
        $has_pay     = $payLogData['has_pay'];

        $userPayLogData = array(
                'type'          =>  0,
                'busisort'      =>  23,
                'busino'        =>  24,
                'pay_make_id'   =>  $pay_make_id,
                'expect'        =>  '',
                'lot_id'        =>  '',
                'user_id'       =>  $user_id,
                'user_name'     =>  $user_name,
                'order_id'      =>  $order_id,
                'pay_money'     =>  $pay_money,
                'pay_poundage'  =>  0.00,
                'has_pay'       =>  $has_pay,
                'add_time'      =>  date('Y-m-d H:i:s',time()),
                'remarks'       =>  '充值订单编号:'.$order_id,
                'admin_remarks' =>  '充值订单编号:'.$order_id,
                'admin_name'    =>  'SYS'
            );

        $SaleUserpaylog     = M('SaleUserpaylog');

        $sale_userpaylog_id = $SaleUserpaylog->add($userPayLogData);

        return $sale_userpaylog_id;
    }
    public function test()
    {
        $res = M('User_achieve')->add(array('uid'=>'1000'));
        dump($res);
    }
    // 添加马甲用户
    public function addUserShow()
    {
        if(IS_POST)
        {
            $User = M('User');

            $UserInfo = M('UserInfo');

            $userData = array();

            $username = trim(I('username'));
            $nickName = trim(I('post.unick',''));
            $password = trim(I('password'));
            $store    = trim(I('store'));

            if(strlen($username) < 11)
            {
                $this->error('请正确填写手机号');
            }

            $isUserExist = $User->where(['phone' => $username])->count('id');

            if($isUserExist)
            {
                $this->error('手机号已存在,请重新填写');
            }

            if(strlen($password) < 6)
            {
                $this->error('请正确填写密码');
            }

            if(strlen($nickName) < 1)
            {
                $this->error('请正确填写昵称');
            }

            $isNicknameExist = $UserInfo->where(['user_name' => $nickName])->count('id');

            if($isNicknameExist)
            {
                $this->error('昵称已存在,请重新填写');
            }

            $userData = array(
                    'phone'       => $username,
                    'passwd'      => md5($password),
                    'is_vest'     => 2,
                    'store_id'    => $store,
                    'business_id' => session('user_auth.id'),
                    'create_time' => time(),
                    'type'        => '4'
                );

                $resultUser = $User->add($userData);

                if($resultUser)
                {
                    $resultUserInfo = $UserInfo->add(['user_id' => $resultUser,'user_name' => $nickName]);

                    if($resultUserInfo)
                    {

                        $userAccountData = array(
                                'use_money' =>  0,
                                'user_id'   =>  $resultUserInfo,
                                'user_name' =>  $nickName
                            );

                        $user_account_id = M('UserAccount')->add($userAccountData);
                        $user_achieve_id = M('User_achieve')->add(array('uid'=>$resultUserInfo));
                        if($user_account_id && $user_achieve_id)
                        {

                            $this->success('用户添加成功', U('user/usersList'));
                        }
                        else
                        {

                            $this->error('用户添加失败，请重新尝试', U('user/addUserShow'));
                        }
                    }
                    else
                    {

                        $this->error('用户添加失败，请重新尝试', U('user/addUserShow'));
                    }
                }
                else
                {

                    $this->error('用户添加失败，请重新尝试', U('user/addUserShow'));
                }

        }
        else
        {
            if (session('superadmin')) {
                $stores = StoreModel::getStore();
            }else{
                $stores = StoreModel::getStore(session('user_auth.id'));
            }

            $this -> assign('stores', $stores);
            $this -> display();
        }
    }

    // 添加马甲用户
    private function addVestUserDatas($vestUserData)
    {
        $nickName    = $vestUserData['nickName'];
        $userNum     = $vestUserData['userNum'];
        $userMonFrom = $vestUserData['userMonFrom'];
        $userMonTo   = $vestUserData['userMonTo'];

        $nickNameDatas = $userNum > 1 || empty($nickName) ? $this->getNickNames($userNum,6) : [$nickName];
        $moneyDatas = $this->getMoneyDatas($userNum,$userMonFrom,$userMonTo);

        $User          = M('User');
        $UserInfo      = M('UserInfo');
        $UserAccount   = M('UserAccount');
        $nickUsedCount = 0;
        foreach ($nickNameDatas as $key => $nickNameData)
        {
            $current_time = time();

            $isExist = $UserInfo -> field(['id'])
                                 -> where(['user_name' => $nickNameData])
                                 -> count();
            // var_dump($isExist);
            if($isExist)
            {
                ++$nickUsedCount;
                continue;
            }

            $User->startTrans();

            $userData = array(
                    'phone'       => $nickNameData,
                    'is_vest'     => 1,
                    'create_time' => $current_time
                );

            $user_id = $User->add($userData);

            if(!$user_id)
            {
                $User->rollback();
                ++$nickUsedCount;
                continue;
            }

            $userInfoData = array(
                    'user_name'       => $nickNameData,
                    'user_id'         => $user_id,
                    'register_time'   => $current_time,
                    'last_login_time' => $current_time
                );

            $user_info_id = $UserInfo->add($userInfoData);

            if(!$user_info_id)
            {
                $User->rollback();
                ++$nickUsedCount;
                continue;
            }

            $userAccountData = array(
                    'use_money' => $moneyDatas[$key],
                    'user_id'   => $user_info_id,
                    'user_name' => $nickNameData
                );

            $user_account_id = $UserAccount->add($userAccountData);

            if(!$user_account_id)
            {
                $User->rollback();
                ++$nickUsedCount;
                continue;
            }

            // shiwutijiao
            $User->commit();
        }

        if($nickUsedCount)
        {
            $vestUserData['userNum'] = $nickUsedCount;

            $this->addVestUserDatas($vestUserData);
        }
    }

    // 获取充值金额数据
    private function getMoneyDatas($number = 1,$moneyFrom = 1,$moneyTo = 1)
    {
        $moneyDatas = array();

        for($i = 0;$i < $number;$i++)
        {
            $moneyDatas[] = mt_rand($moneyFrom,$moneyTo);
        }

        return $moneyDatas;
    }

    // 批量生成昵称
    public function getNickNames($number = 1, $length = 6)
    {
        $nickNameArr = array();

        $number      = $number > 0 ? $number + 1 : 1;

        while(--$number)
        {
            $nickNameArr[] = $this->getNickName($length);
        }

        return $nickNameArr;
    }

    // 生成昵称
    private function getNickName($length = 8)
    {
        // 昵称字符集，可任意添加你需要的字符
        $nickNameChars = array(
                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
                'i', 'j', 'k', 'm', 'n', 'p', 'q', 'r',
                's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
                'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R',
                'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
                '1', '2', '3', '4', '5', '6', '7', '8', '9'
            );

        // 打乱数组
        shuffle($nickNameChars);

        // 从第一个数组元素开始取数组length个数组
        $nickNameArr = array_slice($nickNameChars,0,$length);

        // 连接数组生成昵称
        $nickName = implode($nickNameArr,'');

        return $nickName;
    }

    // 账户明细
    public function accountDetail()
    {
        // 实例化数据表
        $sale_userpaylog_model = M('sale_userpaylog');
        $map = array();// 收集条件

        $user_id  = trim(I('id/d','',''));// id
        $zjType   = trim(I('zjType'));// 流水收支类型
        $starTime = trim(I('starTime'));// 开始时间
        $endTime  = trim(I('endTime'));//  结束世间
        $status   = trim(I('status'));//   流水状态

        //  搜索用户ID
        if ($user_id) {
            $map['sul.user_id'] = trim($user_id);
        }

        //  类型搜索
        if ($zjType || $zjType == '0') {
            $map['sul.type'] = trim($zjType);
        }

        //  流水状态
        if ($status || $status == '0') {
            $map['sul.status'] = trim($status);
        }

        //  时间搜索
        if ($starTime && !$endTime) {
            $map['sul.add_time'] = array('GT', date('Y-m-d H:i:s', strtotime($starTime)));
        }elseif (!$endTime && $endTime){
            $map['sul.add_time'] = array('LT', date('Y-m-d H:i:s', strtotime($endTime)));
        }elseif ($starTime && $endTime){
            $map['sul.add_time'] = array(array('GT', date('Y-m-d H:i:s', strtotime($starTime))), array('LT', date('Y-m-d H:i:s', strtotime($endTime))));
        }

        $join = 'LEFT JOIN tp_base_useriotype but ON sul.busino = but.uo_id';
        // $join2 = 'LEFT JOIN tp_user_order uo ON sul.order_id = uo.order_code';
        $field = 'sul.id, sul.type, sul.busisort, sul.user_id, sul.pay_make_id, sul.expect, sul.user_name, sul.order_id, sul.pay_money, sul.pay_poundage, sul.has_pay, sul.add_time, sul.status, but.uo_note';

        //统计搜索结果
        $dataCount = $sale_userpaylog_model
                                -> alias('sul')
                                -> join($join)
                                // -> join($join2)
                                -> where($map)
                                -> count();

        // 实例化分页类 设置分页条数 默认为10
        $page     = new \Think\Page($dataCount, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page->parameter['search'] = urlencode($search);

        $datas = $sale_userpaylog_model -> alias('sul')
                                -> join($join)
                                // -> join($join2)
                                -> where($map)
                                -> field($field)
                                -> order('sul.id DESC')
                                -> limit($page->firstRow,$page->listRows)
                                -> select();

        $this -> assign('saleUserpaylogDatas', $datas);
        $this -> assign('page', $page->show());
        $this -> assign('zjType', $zjType);
        $this -> assign('starTime', $starTime);
        $this -> assign('endTime', $endTime);
        $this -> assign('status', $status);

        $this -> display();
    }

/* || ========================================================= || */
/*   店主管理
/*   Y    2017-07-27  代完
/* || ========================================================= || */
    public function storeMain()
    {
        // 实例化代理表
        $agent_busi_model = M('agent_busi');

        // 处理信息查看权限
        if(!session('superadmin'))
        {
            $map['id'] = UserModel::getDataAuth ('5');
        }

        $keyword = trim(I('keyword', ''));
        $group   = trim(I('stores', ''));

        // 判断搜索条件
        if ($keyword) {
            $map['agent_name'] = array('LIKE', "%{$keyword}%");
        }

        if ($group) {
            $store_id = M('user_store') -> where(['store_id' => trim($group)]) -> select();
            $where    = '';
            foreach ($store_id as $key => $value) {
                $where .= $value['user_id'] . ',';
            }
            $map['id'] = array('in', rtrim($where, ','));

        }

        $map['idtn'] = '5';

        //统计搜索结果
        $countRes = $agent_busi_model -> where($map) -> count();

        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyword);
        // 获取结果集
        $agents = $agent_busi_model -> field('id, agent_name, idtn, rate_return, amount, over_time, status')
                                    -> where($map)
                                    -> order('id')
                                    -> limit($page -> firstRow.','.$page -> listRows)
                                    -> select();


        //  进行数据赛选
        if (!session('superadmin')) {
            $stores = StoreModel::getStore(session('user_auth.id'));
        }else{
            $stores = StoreModel::getStore();
        }

        $this -> assign('keyword', $keyword);  //传递搜索词
        $this -> assign('groups', $group);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('stores', $stores);
        $this -> assign('agent', $agents);
        $this -> display();
    }

    // 添加店主
    public function addStoreMain ()
    {
        if (!$_POST) {
            $stores = UserModel::getStore(session('user_auth.id'));

            $userSelectStore = StoreModel::setDisplayStore ($stores, '5');

            // 如果没有店铺，则先去添加店铺，再添加店主
            if (!$stores) {
                $this -> error('请先添加店铺', U('store/addStore'));
            }

            $this -> assign('stores', $userSelectStore);
            $this -> assign('idtn', '5');
            $this -> display('edit_agent');

        }else{

            $data = I('post.');

            $group_name = M('agent_busi') -> where(['agent_name' => trim($data['agent_name'])]) -> getField('agent_name');

            if ($group_name) {
                $this -> error('该名称已存在', U('user/addAgent'));
            }

            //  添加店主
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['password']    = md5(trim($data['passwd']));
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(trim($data['over_time']));
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['status']      = trim($data['status']);
            $agent['pid']         = $_SESSION['user_auth']['id'];
            $agent['add_time']    = time();
            $agent['idtn']        = trim($data['idtn']);

            $res = M('agent_busi') -> add($agent);

            if ($res) {

                $uid = M('agent_busi') -> max('id');

                //用户添加成功后添加用户角色表
                $role['role_id'] = $agent['idtn'];
                $role['user_id'] = $uid;

                M('role_user') -> where(['user_id' => $uid]) -> delete();

                M('role_user') -> add($role);

                //添加到关联店铺表
                StoreModel::editUserStore($data['store_id'], $uid, $agent['idtn']);
            }

            if ($res) {
                $this -> success('操作成功', 'storeMain');
            }else{
                $this -> error('操作失败', U('user/addStoreMain'));
            }

        }
    }

    //  修改店主
    public function editStoreMain ()
    {
        if (!$_POST) {
            $agents = M('agent_busi') -> field('id, agent_name, password , rate_return, amount, over_time, status')
                                      -> where(['id' => trim(I('get.id/d', ''))])
                                      -> find();

            //  进行数据赛选
            if (!session('superadmin')) {
                $stores = StoreModel::getStore(session('user_auth.id'));
            }else{
                $stores = StoreModel::getStore();
            }

            $uid = trim(I('get.id/d', ''));

            $userSelectStore = StoreModel::setSelectDisStore($uid, $stores, '5');

            $this -> assign('stores', $userSelectStore);
            $this -> assign('agent', $agents);
            $this -> assign('idtn', '5');
            $this -> assign('edit', 'update');
            $this -> display('edit_agent');
        }else{
            $data = I('post.', '');

            $condition['id']      = trim($data['id']);
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(str_replace("-","-", trim($data['over_time'])));
            $agent['status']      = trim($data['status']);

            if ($data['passwd']) {
                $agent['password'] = md5(trim($data['passwd']));
            }

            /*********************************@qiao****************************************/
            $agent_info = M('agent_busi')->where($condition)->find();
            if($agent_info['agent_name']!=$agent['agent_name']){
                $agent_name_verify = M('agent_busi')->where('agent_name="'.$agent['agent_name'].'"')->find();
            }
            if(!empty($agent_name_verify)){
                $this->error('名字不可重复');
            }
            /*********************************end @qiao****************************************/

            $result = M('agent_busi') -> where($condition)
                                      -> data($agent)
                                      -> save();

            //添加到关联店铺表
            $res = StoreModel::editUserStore($data['store_id'], $data['id']);

            if ($result || $res) {

                $this -> success('修改成功',  U('user/storeMain'));
            }else{
                $this -> error('修改失败', U('user/editStoreMain',  $condition));
            }
        }
    }

/* || ========================================================= || */
/*   店长管理
/*   Y    2017-06-19    完
/* || ========================================================= || */
    public function agent ()
    {
    	// 实例化表
        $agent_busi_model = M('agent_busi');

        // 处理信息查看权限
        if(!session('superadmin'))
        {
            $map['id'] = UserModel::getDataAuth ('2');
        }

        $keyword = trim(I('keyword', ''));
        $group   = trim(I('stores', ''));

        // 判断搜索条件
        if ($keyword) {
            $map['agent_name'] = array('LIKE', "%{$keyword}%");
        }

        if ($group) {
            $store_id = M('user_store') -> where(['store_id' => trim($group)]) -> select();
            $where = '';
            foreach ($store_id as $key => $value) {
                $where .= $value['user_id'] . ',';
            }
            $map['id'] = array('in', rtrim($where, ','));

        }

        //  获取角色id
        $map['idtn'] = '2';

        //统计搜索结果
        $countRes = $agent_busi_model -> where($map) -> count();

        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyword);
        // 获取结果集
        $agents = array();
        $agents = $agent_busi_model -> field('id, agent_name, idtn, rate_return, amount, over_time, status')
                                    -> where($map)
                                    -> order('id')
                                    -> limit($page -> firstRow.','.$page -> listRows)
                                    -> select();

        //  进行数据赛选
        if (!session('superadmin')) {
            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);
        }else{
            $stores = StoreModel::getStore();
        }

        $this -> assign('stores', $stores);  //传递搜索词
        $this -> assign('keyword', $keyword);  //传递搜索词
        $this -> assign('groups', $group);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('agent', $agents);
        $this -> display();
    }

    //  添加店长
    public function addAgent ()
    {

        if (!IS_POST) {
            $stores = StoreModel::getStore(session('user_auth.id'));

            $userSelectStore = StoreModel::setDisplayStore ($stores, '2');

            $this -> assign('stores', $userSelectStore);
            $this -> assign('idtn', '2');
            $this -> assign('edit', 'update');
            $this -> display('edit_agent');

        }else{
            $data = I('post.', '');

            $group_name = M('agent_busi') -> where(['agent_name' => trim($data['agent_name'])]) -> getField('agent_name');

            if ($group_name) {
                $this -> error('该名称已存在', U('user/addAgent'));
            }

            //  添加代理
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['password']    = md5(trim($data['passwd']));
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(trim($data['over_time']));
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['status']      = trim($data['status']);
            $agent['pid']         = session('user_auth.id');
            $agent['add_time']    = time();
            $agent['idtn']        = trim($data['idtn']);

            $res = M('agent_busi') -> data($agent) -> add();

            if ($res) {
                $uid = M('agent_busi') -> max('id');

                //用户添加成功后添加用户角色表
                $role['role_id'] = $agent['idtn'];
                $role['user_id'] = $uid;

                M('role_user')->where(['user_id' => $uid])->delete();

                M('role_user')->add($role);

                //添加到关联店铺表
                StoreModel::editUserStore($data['store_id'], $uid);
            }

            if ($res) {
                $this -> success('操作成功', 'agent');
            }else{
                $this -> error('操作失败', U('user/addAgent'));
            }

        }
    }

    // 编辑修改
    public function editAgent ()
    {
        if (!IS_POST) {

            $agents = M('agent_busi') -> field('id, agent_name, password , rate_return, amount, over_time, status')
                                      -> where(['id' => trim(I('get.id/d', ''))])
                                      -> find();

            //  进行数据赛选
            if (!session('superadmin')) {
                $stores = StoreModel::getStore($_SESSION['user_auth']['id']);
            }else{
                $stores = StoreModel::getStore();
            }

            $uid = trim(I('get.id/d', ''));

            $res = StoreModel::setSelectDisStore($uid, $stores, '2');

            $this -> assign('stores', $res);

            $this -> assign('agent', $agents);
            $this -> assign('edit', 'update');
            $this -> display('edit_agent');
        }else{
            $data = I('post.', '');

            $condition['id']      = trim($data['id']);
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(str_replace("-","-", trim($data['over_time'])));
            $agent['status']      = trim($data['status']);


            /*********************************@qiao****************************************/
            $agent_info = M('agent_busi')->where($condition)->find();
            if($agent_info['agent_name']!=$agent['agent_name']){
                $agent_name_verify = M('agent_busi')->where('agent_name="'.$agent['agent_name'].'"')->find();
            }
            if(!empty($agent_name_verify)){
                $this->error('名字不可重复');
            }
            /*********************************end @qiao****************************************/

            if ($data['passwd']) {
                $agent['password'] = md5(trim($data['passwd']));
            }

            $result = M('agent_busi') -> where($condition)
                                      -> data($agent)
                                      -> save();

            $res = StoreModel::editUserStore($data['store_id'], $data['id']);

            if ($result || $res) {

                $this -> success('修改成功',  U('user/agent'));
            }else{
                $this -> error('修改失败', U('user/editagent',  $condition));
            }

        }
    }

    // ajax  编辑
    public function ajaxAgent ()
    {

        $status      = trim(I('post.ag_status', ''));
        $ag_id['id'] = trim(I('post.ag_id/d', ''));

        if ($ag_id) {

            if ($status) {

                $editStatus['status'] = ($status == '启用') ? '0' : '1';

                $result = M('agent_busi') -> where($ag_id)
                                          -> data($editStatus)
                                          -> save();

                if ($result) {
                    $returnData['status'] = 1;
                    $returnData['msg']    = $editStatus['status'];
                }else{
                    $returnData['status'] = 0;
                }

            }else{

                $row = M('agent_busi') -> where($ag_id) -> delete();

                if($row){
                    M('role_user') -> where(['user_id' => trim(I('post.ag_id/d', ''))]) -> delete();
                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }

/* || ======================================================================== || */
/*   店员管理
/*   Y    2017-06-22   完
/* || ======================================================================== || */
    public function clerk ()
    {
    	// 实例化标签表
        $agent_busi_model = M('agent_busi');

        // 处理信息查看权限
        if(!session('superadmin'))
        {
            $map['ab.id'] = UserModel::getDataAuth ('3');
        }

        $keyWord  = trim(I('key', ''));
        $group    = trim(I('stores', ''));
        $agent_id = trim(I('id', ''));

        // 判断搜索条件
        if ($keyWord) {
            $map['agent_name'] = array('LIKE', "%{$keyWord}%");
        }

        if ($group) {
            $store_id = M('user_store') -> where(['store_id' => trim($group)]) -> select();
            $where = '';
            foreach ($store_id as $key => $value) {
                $where .= $value['user_id'] . ',';
            }
            $map['ab.id'] = array('in', rtrim($where, ','));

        }

        if ($agent_id) {

            $map['ab.id'] = UserModel::getDataAuth ('3', $agent_id);
        }

        //  获取角色id
        $map['idtn'] = '3';

        //统计搜索结果
        $countRes = $agent_busi_model  -> alias('ab')
                                      -> join('left join tp_business_level bl ON bl.id = ab.level_id')
                                      -> where($map)
                                      -> count();
        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);

        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyWord);
        // 获取结果集
        $business = $agent_busi_model -> alias('ab')
                                      -> join('left join tp_business_level bl ON bl.id = ab.level_id')
                                      -> where($map)
                                      -> field('ab.id, agent_name, phone, monthly_sales, total_sales, add_time, ab.status, bl.level_name, bl.id level_id')
                                      -> order('ab.id')
                                      -> limit($page -> firstRow.','.$page -> listRows)
                                      -> select();

        //统计每个业务员的客户
        $businessInfo = array();
        foreach ($business as $key => $value) {
            $businessInfo[$key] = $value;
            $totals = M('user') -> where(['business_id' => $value['id']]) -> count('id');

            $businessInfo[$key]['total'] = $totals;

            $store = M('user_store') -> alias('us') -> join('LEFT JOIN tp_store s ON us.store_id = s.id') -> where(['us.user_id' => $value['id']]) -> getField('s.store_name');
            $businessInfo[$key]['store_name'] = $store;
        }

        //  进行数据赛选
        if (!session('superadmin')) {
            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);
        }else{
            $stores = StoreModel::getStore();
        }

        $this -> assign('stores', $stores);
        $this -> assign('groups', $group);  //传递搜索词
        $this -> assign('keyWord', $keyWord);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('business', $businessInfo);
        $this -> display();
    }

    //  添加店员
    public function addClerk ()
    {
        if (!IS_POST) {

            $this -> businessLevel = M('business_level') -> where(['level_status' => 1]) -> select();

            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);

            //  如果这个店长只有一个店铺，则店员默认在该店铺下
            if (count($stores) > '1') {
                $this -> assign('stores', $stores);
            }else{
                $this -> assign('store_id', $stores['0']['id']);
            }

            $this -> assign('idtn', '3');
            $this -> display('edit_clerk');

        }else{
            $data = I('post.', '');

            // 验证用户名合法
            $name = M('agent_busi') -> where(['agent_name' => I('agent_name', '')]) -> getField('agent_name');

            if ($name) {
                $this -> error('店员名称已存在', U('user/addClerk'));
            }

            //  整理入库数据
            $agent['agent_name'] = $data['agent_name'];
            $agent['password']   = md5($data['passwd']);
            $agent['pid']        = $_SESSION['user_auth']['id'];
            $agent['phone']      = $data['phone'];
            $agent['level_id']   = $data['level_id'];
            $agent['idtn']       = $data['idtn'];
            $agent['status']     = $data['status'];
            $agent['add_time']   = time();

            $res = M('agent_busi') -> data($agent) -> add();

            if ($res) {

                $uid = M('agent_busi') -> max('id');
                // 处理店员所属店铺
                if (!empty($data['store_id'])) {
                    StoreModel::editUserStore($data['store_id'], $uid);
                }

                //用户添加成功后添加用户角色表
                $role['role_id'] = $agent['idtn'];
                $role['user_id'] = $uid;

                $result = M('role_user')->add($role);

                if ($result) {
                    $this -> success('操作成功', U('user/clerk'));
                }else{
                    $this -> error('操作失败', U('user/addClerk'));
                }
            }
        }

    }

    //  修改店员信息
    public function editClerk ()
    {
        if (!IS_POST) {

            $this -> businessLevel = M('business_level') -> where(['level_status' => 1]) -> select();

            $this -> business = M('agent_busi') -> join('tp_business_level bl ON bl.id = tp_agent_busi.level_id')
                                              -> where(['tp_agent_busi.id' => I('id/d', '')])
                                              -> field('tp_agent_busi.id, agent_name, phone, status, level_name, bl.id level_id')
                                              -> find();

            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);

            $uid = trim(I('get.id/d', ''));
            $userSelectStore = StoreModel::setSelectStore ($stores, $uid);

            $this -> assign('stores', $userSelectStore);

            $this -> display('edit_clerk');

        }else{
            $data = I('post.', '');

            $name = M('agent_busi') -> where(['agent_name' => I('agent_name', ''), 'id' => array('neq', I('id/d', ''))]) -> getField('agent_name');

            if ($name) $this -> error('用户名已存在', U('user/addClerk'));

            // 整理入库数据
            $agent['agent_name'] = trim($data['agent_name']);
            $agent['phone']      = trim($data['phone']);
            $agent['level_id']   = trim($data['level_id']);
            $agent['status']     = trim($data['status']);
            if (!empty($data['passwd'])) {
                $agent['password'] = md5(trim($data['passwd']));
            }


            $result = M('agent_busi') -> where(['id' => I('id/d', '')])
                                      -> data($agent)
                                      -> save();

            if ($result) {
                $this -> success('操作成功', U('user/clerk'));
            }else{
                $this -> error('操作失败', U('user/addClerk', array('id' => I('id/d', ''))));
            }
        }

    }

    //   ajax 修改
    public function ajaxBusiness ()
    {
        $status          = trim(I('post.edit_status', ''));
        $condition['id'] = trim(I('post.id/d', ''));

        if ($condition) {

            if ($status) {

                $editStatus['status'] = ($status == '启用') ? '0' : '1';

                $result = M('agent_busi') -> where($condition)
                                        -> data($editStatus)
                                        -> save();

                if ($result) {
                    $returnData['status'] = 1;
                    $returnData['msg']    = $editStatus['status'];
                }else{
                    $returnData['status'] = 0;
                }

            }else{

                $row = M('agent_busi') -> where($condition) -> delete();

                if($row){
                    M('role_user') -> where(['user_id' => trim(I('post.id/d', ''))]) -> delete();

                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }


/* || ========================================================= || */
/*   渠道经理管理
/*   Y    2017-07-27  代完
/* || ========================================================= || */
    public function manager()
    {
        // 实例化代理表
        $agent_busi_model = M('agent_busi');

        // 处理信息查看权限
        if(!session('superadmin'))
        {
            $map['id'] = UserModel::getDataAuth ('10');
        }

        $keyword = trim(I('keyword', ''));
        $group   = trim(I('stores', ''));
//        var_dump($group);die;

        // 判断搜索条件
        if ($keyword) {
            $map['agent_name'] = array('LIKE', "%{$keyword}%");
        }

        if ($group) {
            $store_id = M('user_store') -> where(['store_id' => trim($group)]) -> select();
//            var_dump($store_id);die;
            $where    = '';
            foreach ($store_id as $key => $value) {
                $where .= $value['user_id'] . ',';
            }
            $map['id'] = array('in', rtrim($where, ','));
//            var_dump($map['id']);die;

        }

        $map['idtn'] = '10';

        //统计搜索结果
        $countRes = $agent_busi_model -> where($map) -> count();

        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyword);
        // 获取结果集
        $agents = $agent_busi_model -> field('id, agent_name, idtn, rate_return, amount, over_time, status')
                                    -> where($map)
                                    -> order('id')
                                    -> limit($page -> firstRow.','.$page -> listRows)
                                    -> select();


        //  进行数据赛选
        if (!session('superadmin')) {
            $stores = StoreModel::getStore(session('user_auth.id'));
        }else{
            $stores = StoreModel::getStore();
        }

//        var_dump($stores);die;
        $this -> assign('keyword', $keyword);  //传递搜索词
        $this -> assign('groups', $group);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('stores', $stores);
        $this -> assign('agent', $agents);
        $this -> display();

    }

    // 添加渠道经理
    public function addManager ()
    {
        if (!$_POST) {
            $stores = UserModel::getStore(session('user_auth.id'));

            $userSelectStore = StoreModel::setDisplayStore ($stores, '10');

            // 如果没有店铺，则先去添加店铺，再添加店主
            if (!$stores) {
                $this -> error('请先添加店铺', U('store/addStore'));
            }

            $this -> assign('stores', $userSelectStore);
            $this -> assign('idtn', '10');
            $this -> display('edit_man');

        }else{

            $data = I('post.');

            $group_name = M('agent_busi') -> where(['agent_name' => trim($data['agent_name'])]) -> getField('agent_name');

            if ($group_name) {
                $this -> error('该名称已存在', U('user/addManager'));
            }

            //  添加渠道经理
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['password']    = md5(trim($data['passwd']));
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(trim($data['over_time']));
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['status']      = trim($data['status']);
            $agent['pid']         = $_SESSION['user_auth']['id'];
            $agent['add_time']    = time();
            $agent['idtn']        = trim($data['idtn']);

            $res = M('agent_busi') -> add($agent);

            if ($res) {

                $uid = M('agent_busi') -> max('id');

                //用户添加成功后添加用户角色表
                $role['role_id'] = $agent['idtn'];
                $role['user_id'] = $uid;

                M('role_user') -> where(['user_id' => $uid]) -> delete();

                M('role_user') -> add($role);

                //添加到关联店铺表
                StoreModel::editUserStore($data['store_id'], $uid, $agent['idtn']);
            }

            if ($res) {
                $this -> success('操作成功', 'manager');
            }else{
                $this -> error('操作失败', U('user/addManager'));
            }

        }
    }

    //  修改渠道经理
    public function editManager ()
    {
        if (!$_POST) {
            $agents = M('agent_busi') -> field('id, agent_name, password , rate_return, amount, over_time, status')
                                      -> where(['id' => trim(I('get.id/d', ''))])
                                      -> find();

            //  进行数据赛选
            if (!session('superadmin')) {
                $stores = StoreModel::getStore(session('user_auth.id'));
            }else{
                $stores = StoreModel::getStore();
            }

            $uid = trim(I('get.id/d', ''));

            $userSelectStore = StoreModel::setSelectDisStore($uid, $stores, '10');

            $this -> assign('stores', $userSelectStore);
            $this -> assign('agent', $agents);
            $this -> assign('idtn', '10');
            $this -> assign('edit', 'update');
            $this -> display('edit_man');
        }else{
            $data = I('post.', '');

            $condition['id']      = trim($data['id']);
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(str_replace("-","-", trim($data['over_time'])));
            $agent['status']      = trim($data['status']);

            if ($data['passwd']) {
                $agent['password'] = md5(trim($data['passwd']));
            }

            /*********************************@qiao****************************************/
            $agent_info = M('agent_busi')->where($condition)->find();
            if($agent_info['agent_name']!=$agent['agent_name']){
                $agent_name_verify = M('agent_busi')->where('agent_name="'.$agent['agent_name'].'"')->find();
            }
            if(!empty($agent_name_verify)){
                $this->error('名字不可重复');
            }
            /*********************************end @qiao****************************************/

            $result = M('agent_busi') -> where($condition)
                                      -> data($agent)
                                      -> save();

            //添加到关联店铺表
            $res = StoreModel::editUserStore($data['store_id'], $data['id']);

            if ($result || $res) {

                $this -> success('修改成功',  U('user/manager'));
            }else{
                $this -> error('修改失败', U('user/editManager',  $condition));
            }
        }
    }

/* || ========================================================= || */
/*   渠道主管管理
/*   Y    2017-06-19    完
/* || ========================================================= || */
    public function director ()
    {
        // 实例化表
        $agent_busi_model = M('agent_busi');

        // 处理信息查看权限
        if(!session('superadmin'))
        {
            $map['id'] = UserModel::getDataAuth ('11');
        }

        $keyword = trim(I('keyword', ''));
        $group   = trim(I('stores', ''));

        // 判断搜索条件
        if ($keyword) {
            $map['agent_name'] = array('LIKE', "%{$keyword}%");
        }

        if ($group) {
            $store_id = M('user_store') -> where(['store_id' => trim($group)]) -> select();
            $where = '';
            foreach ($store_id as $key => $value) {
                $where .= $value['user_id'] . ',';
            }
            $map['id'] = array('in', rtrim($where, ','));

        }

        //  获取角色id
        $map['idtn'] = '11';

        //统计搜索结果
        $countRes = $agent_busi_model -> where($map) -> count();

        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyword);
        // 获取结果集
        $agents = array();
        $agents = $agent_busi_model -> field('id, agent_name, idtn, rate_return, amount, over_time, status')
                                    -> where($map)
                                    -> order('id')
                                    -> limit($page -> firstRow.','.$page -> listRows)
                                    -> select();

        //  进行数据赛选
        if (!session('superadmin')) {
            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);
        }else{
            $stores = StoreModel::getStore();
        }

        $this -> assign('stores', $stores);  //传递搜索词
        $this -> assign('keyword', $keyword);  //传递搜索词
        $this -> assign('groups', $group);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('agent', $agents);
        $this -> display();
    }

    //  添加渠道主管
    public function addDirector ()
    {

        if (!IS_POST) {

            $stores = StoreModel::getStore(session('user_auth.id'));

            $userSelectStore = StoreModel::setDisplayStore ($stores, '11');

            $this -> assign('stores', $userSelectStore);
            $this -> assign('idtn', '11');
            $this -> assign('edit', 'update');
            $this -> display('edit_man');

        }else{
            $data = I('post.', '');

            $group_name = M('agent_busi') -> where(['agent_name' => trim($data['agent_name'])]) -> getField('agent_name');

            if ($group_name) {
                $this -> error('该名称已存在', U('user/addDirector'));
            }

            //  添加渠道主管
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['password']    = md5(trim($data['passwd']));
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(trim($data['over_time']));
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['status']      = trim($data['status']);
            $agent['pid']         = session('user_auth.id');
            $agent['add_time']    = time();
            $agent['idtn']        = trim($data['idtn']);

            $res = M('agent_busi') -> data($agent) -> add();

            if ($res) {
                $uid = M('agent_busi') -> max('id');

                //用户添加成功后添加用户角色表
                $role['role_id'] = $agent['idtn'];
                $role['user_id'] = $uid;

                M('role_user')->where(['user_id' => $uid])->delete();

                M('role_user')->add($role);

                //添加到关联店铺表
                StoreModel::editUserStore($data['store_id'], $uid, $agent['idtn']);
            }

            if ($res) {
                $this -> success('操作成功', 'director');
            }else{
                $this -> error('操作失败', U('user/addDirector'));
            }

        }
    }

    // 编辑修改渠道主管
    public function editDirector ()
    {
        if (!IS_POST) {

            $agents = M('agent_busi') -> field('id, agent_name, password , rate_return, amount, over_time, status')
                                      -> where(['id' => trim(I('get.id/d', ''))])
                                      -> find();

            //  进行数据赛选
            if (!session('superadmin')) {
                $stores = StoreModel::getStore($_SESSION['user_auth']['id']);
            }else{
                $stores = StoreModel::getStore();
            }

            $uid = trim(I('get.id/d', ''));

            $res = StoreModel::setSelectDisStore($uid, $stores, '11');

            $this -> assign('stores', $res);

            $this -> assign('agent', $agents);
            $this -> assign('edit', 'update');
            $this -> assign('idtn', '11');
            $this -> display('edit_man');
        }else{
            $data = I('post.', '');

            $condition['id']      = trim($data['id']);
            $agent['agent_name']  = trim($data['agent_name']);
            $agent['rate_return'] = trim($data['rate_return']);
            $agent['amount']      = trim($data['amount']);
            $agent['over_time']   = strtotime(str_replace("-","-", trim($data['over_time'])));
            $agent['status']      = trim($data['status']);


            /*********************************@qiao****************************************/
            $agent_info = M('agent_busi')->where($condition)->find();
            if($agent_info['agent_name']!=$agent['agent_name']){
                $agent_name_verify = M('agent_busi')->where('agent_name="'.$agent['agent_name'].'"')->find();
            }
            if(!empty($agent_name_verify)){
                $this->error('名字不可重复');
            }
            /*********************************end @qiao****************************************/

            if ($data['passwd']) {
                $agent['password'] = md5(trim($data['passwd']));
            }

            $result = M('agent_busi') -> where($condition)
                                      -> data($agent)
                                      -> save();

            $res = StoreModel::editUserStore($data['store_id'], $data['id'], $data['idtn']);

            if ($result || $res) {

                $this -> success('修改成功',  U('user/director'));
            }else{
                $this -> error('修改失败', U('user/editDirector',  $condition));
            }

        }
    }

    // ajax  编辑渠道主管
    public function ajaxDirector ()
    {

        $status      = trim(I('post.ag_status', ''));
        $ag_id['id'] = trim(I('post.ag_id/d', ''));

        if ($ag_id) {

            if ($status) {

                $editStatus['status'] = ($status == '启用') ? '0' : '1';

                $result = M('agent_busi') -> where($ag_id)
                                          -> data($editStatus)
                                          -> save();

                if ($result) {
                    $returnData['status'] = 1;
                    $returnData['msg']    = $editStatus['status'];
                }else{
                    $returnData['status'] = 0;
                }

            }else{

                $row = M('agent_busi') -> where($ag_id) -> delete();

                if($row){
                    M('role_user') -> where(['user_id' => trim(I('post.ag_id/d', ''))]) -> delete();
                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }

/* || ======================================================================== || */
/*   渠道业务员管理
/*   Y    2017-06-22   完
/* || ======================================================================== || */
    public function salesman ()
    {
        // 实例化标签表
        $agent_busi_model = M('agent_busi');

        // 处理信息查看权限
        if(!session('superadmin'))
        {
            $map['ab.id'] = UserModel::getDataAuth ('12');
        }

        $keyWord  = trim(I('key', ''));
        $group    = trim(I('stores', ''));
        $agent_id = trim(I('id', ''));

        // 判断搜索条件
        if ($keyWord) {
            $map['agent_name'] = array('LIKE', "%{$keyWord}%");
        }

        if ($group) {
            $store_id = M('user_store') -> where(['store_id' => trim($group)]) -> select();
            $where = '';
            foreach ($store_id as $key => $value) {
                $where .= $value['user_id'] . ',';
            }
            $map['ab.id'] = array('in', rtrim($where, ','));

        }

        if ($agent_id) {

            $map['ab.id'] = UserModel::getDataAuth ('12', $agent_id);
        }

        //  获取角色id
        $map['idtn'] = '12';
        // dump($map);die;
        //统计搜索结果
        $countRes = $agent_busi_model  -> alias('ab')
                                      -> join('left join tp_business_level bl ON bl.id = ab.level_id')
                                      -> where($map)
                                      -> count();
        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);

        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyWord);
        // 获取结果集
        $business = $agent_busi_model -> alias('ab')
                                      -> join('left join tp_business_level bl ON bl.id = ab.level_id')
                                      -> where($map)
                                      -> field('ab.id, agent_name, phone, monthly_sales, total_sales, add_time, ab.status, bl.level_name, bl.id level_id')
                                      -> order('ab.id')
                                      -> limit($page -> firstRow.','.$page -> listRows)
                                      -> select();

        //统计每个业务员的客户
        $businessInfo = array();
        foreach ($business as $key => $value) {
            $businessInfo[$key] = $value;
            $totals = M('user') -> where(['business_id' => $value['id']]) -> count('id');

            $businessInfo[$key]['total'] = $totals;

            $store = M('user_store') -> alias('us') -> join('LEFT JOIN tp_store s ON us.store_id = s.id') -> where(['us.user_id' => $value['id']]) -> getField('s.store_name');
            $businessInfo[$key]['store_name'] = $store;
        }

        //  进行数据赛选
        if (!session('superadmin')) {
            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);
        }else{
            $stores = StoreModel::getStore();
        }

        $this -> assign('stores', $stores);
        $this -> assign('groups', $group);  //传递搜索词
        $this -> assign('keyWord', $keyWord);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('business', $businessInfo);
        $this -> display();
    }

    //  添加业务员
    public function addSalesman ()
    {
        if (!IS_POST) {

            $this -> businessLevel = M('business_level') -> where(['level_status' => 1]) -> select();

            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);

            //  如果这个店长只有一个店铺，则店员默认在该店铺下
            if (count($stores) > '1') {
                $this -> assign('stores', $stores);
            }else{
                $this -> assign('store_id', $stores['0']['id']);
            }

            

            $this -> assign('idtn', '12');
            $this -> display('edit_sale');

        }else{
            $data = I('post.', '');

            // 验证用户名合法
            $name = M('agent_busi') -> where(['agent_name' => I('agent_name', '')]) -> getField('agent_name');

            if ($name) {
                $this -> error('店员名称已存在', U('user/addSalesman'));
            }
          
            //  整理入库数据
            $agent['agent_name'] = $data['agent_name'];
            $agent['password']   = md5($data['passwd']);
            $agent['pid']        = $_SESSION['user_auth']['id'];
            $agent['phone']      = $data['phone'];
            $agent['level_id']   = $data['level_id'];
            $agent['idtn']       = $data['idtn'];
            $agent['status']     = $data['status'];
            $agent['add_time']   = time();

            // dump($data);die;
            $res = M('agent_busi') -> data($agent) -> add();

            if ($res) {

                $uid = M('agent_busi') -> max('id');
                // 处理店员所属店铺
                if (!empty($data['store_id'])) {
                    StoreModel::editUserStore($data['store_id'], $uid, $agent['idtn']);
                }else{
                    StoreModel::editUserStore($data['store_id'], $uid, $agent['idtn']);
                }

                //用户添加成功后添加用户角色表
                $role['role_id'] = $agent['idtn'];
                $role['user_id'] = $uid;

                $result = M('role_user')->add($role);

                if ($result) {
                    $this -> success('操作成功', U('user/salesman'));
                }else{
                    $this -> error('操作失败', U('user/addSalesman'));
                }
            }
        }

    }

    //  修改店员信息
    public function editSalesman ()
    {
        if (!IS_POST) {

            $this -> businessLevel = M('business_level') -> where(['level_status' => 1]) -> select();

            $this -> business = M('agent_busi') -> join('tp_business_level bl ON bl.id = tp_agent_busi.level_id')
                                              -> where(['tp_agent_busi.id' => I('id/d', '')])
                                              -> field('tp_agent_busi.id, agent_name, phone, status, level_name, bl.id level_id')
                                              -> find();

            $stores = StoreModel::getStore($_SESSION['user_auth']['id']);

            $uid = trim(I('get.id/d', ''));
            $userSelectStore = StoreModel::setSelectStore ($stores, $uid);

            $this -> assign('stores', $userSelectStore);

            $this -> display('edit_sale');

        }else{
            $data = I('post.', '');

            $name = M('agent_busi') -> where(['agent_name' => I('agent_name', ''), 'id' => array('neq', I('id/d', ''))]) -> getField('agent_name');

            if ($name) $this -> error('用户名已存在', U('user/addClerk'));

            // 整理入库数据
            $agent['agent_name'] = trim($data['agent_name']);
            $agent['phone']      = trim($data['phone']);
            $agent['level_id']   = trim($data['level_id']);
            $agent['status']     = trim($data['status']);
            if (!empty($data['passwd'])) {
                $agent['password'] = md5(trim($data['passwd']));
            }

            $result = M('agent_busi') -> where(['id' => I('id/d', '')])
                                      -> data($agent)
                                      -> save();

            if ($result) {
                $this -> success('操作成功', U('user/salesman'));
            }else{
                $this -> error('操作失败', U('user/addSalesman', array('id' => I('id/d', ''))));
            }
        }

    }

    //   ajax 修改
    public function ajaxSalesman ()
    {
        $status          = trim(I('post.edit_status', ''));
        $condition['id'] = trim(I('post.id/d', ''));

        if ($condition) {

            if ($status) {

                $editStatus['status'] = ($status == '启用') ? '0' : '1';

                $result = M('agent_busi') -> where($condition)
                                        -> data($editStatus)
                                        -> save();

                if ($result) {
                    $returnData['status'] = 1;
                    $returnData['msg']    = $editStatus['status'];
                }else{
                    $returnData['status'] = 0;
                }

            }else{

                $row = M('agent_busi') -> where($condition) -> delete();

                if($row){
                    M('role_user') -> where(['user_id' => trim(I('post.id/d', ''))]) -> delete();

                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }

/*|| =============================================================== || */
/*   彩民池管理  -----    彩民池
/*   Y    2017-06-22   完
/*|| =============================================================== || */
    public function clerkList ()
    {
        $business_group_model = M('business_group');

        $keyWord     = I('key', '');
        $status      = I('post.status', '');
        $business_id = I('get.id/d', '');

        // 超管可查看平台所有会员 不需要处理
        if(!session('superadmin'))
        {
            $map['business_id'] = UserModel::getLotPond();
        }

        //统计搜索结果
        $countRes = $business_group_model -> alias('bg')
                        -> join('left join tp_agent_busi ab ON bg.business_id = ab.id')
                        -> join('LEFT JOIN tp_role r ON r.id = ab.idtn')
                        -> join('LEFT JOIN tp_sector s ON r.sector_id = s.id')
                        -> where($map)
                        -> count();

        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyWord);
        // 获取结果集
        $businessGroup = $business_group_model
                        -> alias('bg')
                        -> join('left join tp_agent_busi ab ON bg.business_id = ab.id')
                        -> join('LEFT JOIN tp_role r ON r.id = ab.idtn')
                        -> join('LEFT JOIN tp_sector s ON r.sector_id = s.id')
                        -> where($map)
                        -> field('bg.id, bg.user_phone, bg.business_id, bg.add_time, bg.status, bg.remark, ab.agent_name, s.sector_name')
                        -> order('bg.status DESC, bg.id')
                        -> limit($page -> firstRow.','.$page -> listRows)
                        -> select();

        //  释放私有会员池
        UserModel::releaseMemberPool ();

        $this -> assign('keyWord', $keyWord);  //传递搜索词
        $this -> assign('status', $status);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('businessGroup', $businessGroup);
        $this -> display();
    }

    //  添加彩民池
    public function addClient ()
    {
        if (!IS_POST) {

            if (
                session('user_auth.idtn') == '5' ||
                session('user_auth.idtn') == '2' ||
                session('user_auth.idtn') == '10' ||
                session('user_auth.idtn') == '11'
                )
            {
                $stores = StoreModel::getStore(session('user_auth.id'));

                $this -> assign('stores', $stores);
            }

            $this -> display('edit_client');

        }else{

            $data = I('post.', '');

            $user_phone = M('business_group') -> where(['user_phone' => trim($data['user_phone'])]) -> getField('user_phone');

            if ($user_phone) $this -> error('手机号已存在', U('user/addClient'));

            $group['user_phone']  = trim($data['user_phone']);
            $group['add_time']    = time();
            $group['business_id'] = session('user_auth.id');

            //           店长，店员，店主
            $busi = array('2', '3', '5');
            //  如果添加彩民池的是：店长，店员，店主， 则彩民池信息为私有的
            $group['status'] = (in_array(session('user_auth.idtn'), $busi)) ? '4' : '1';

            if ($data['store_id']) {
                $group['store_id'] = trim($data['store_id']);
            }else{
                $group['store_id'] = M('User_store')->where(['user_id'=>session('user_auth.id')])->getField('store_id');

            }
 
            $agent_name = M('agent_busi') -> where(['id' => session('user_auth.id')]) -> getField('agent_name');

            $group['remark'] = trim($data['remark']) ? trim($data['remark']) : session('user_auth.role') .': '. $agent_name . '添加';

            $result = M('business_group') -> data($group) -> add();

            if ($result) {
                $this -> success('操作成功', U('user/clerkList'));
            }else{
                $this -> error('操作失败', U('user/addClient'));
            }
        }

    }

    //  批量添加会员池
    public function batchClient ()
    {
        if (!IS_POST) {

            if (session('user_auth.idtn') == '5' || session('user_auth.idtn') == '2')
            {
                $stores = StoreModel::getStore(session('user_auth.id'));

                $this -> assign('stores', $stores);
            }

            $this -> display('batch_client');

        }else{
            //获取上传
            $data = $_FILES['client'];

            //获取文件后缀名
            $fix = pathinfo($data['name'], PATHINFO_EXTENSION);

            if ($fix != 'csv') {
                $this -> error('上传格式有误', U('user/batchClient'));
            }

            $info = file($data['tmp_name']);

            $line_number = 0;

            //           店长，店员，店主
            $busi = array('2', '3', '5');
            //  如果添加彩民池的是：店长，店员，店主， 则彩民池信息为私有的
            $infos['status']      = (in_array(session('user_auth.idtn'), $busi)) ? '4' : '1';
            $infos['business_id'] = session('user_auth.id');

            if (trim(I('store_id'))) {
                $infos['store_id'] = trim(I('store_id'));
            }

            $agent_name = M('agent_busi') -> where(['id' => session('user_auth.id')]) -> getField('agent_name');

            foreach ($info as $key => $value) {
                if ($line_number == 0) {

                    $line_number++;
                    continue;
                }

                $value = mb_convert_encoding($value, "UTF-8", "GBK");

                $datas = explode(',', $value);

                $infos['user_phone']  = $datas[0];
                $infos['add_time']    = time();
                $infos['remark']      = trim($datas[1]) ? trim($datas[1]) : session('user_auth.role') .': '. $agent_name . '添加';

                $user_phone = M('business_group') -> where(['user_phone' => $infos['user_phone']]) -> getField('user_phone');

                if ($user_phone || empty($infos['user_phone'])) {
                    continue;
                }

                $result = M('business_group') -> data($infos) -> add();

            }

            if ($result) {
                $this -> success('操作成功', U('user/clerkList'));
            }else{
                $this -> error('操作失败', U('user/batchClient'));
            }

        }
    }

    //  下载模板
    public function downloadBatchClient ()
    {
        header('Content-type: text/csv; charset=gbk');
        header('Content-Disposition: attachment; filename=batchClient'.date('his', time()).'.csv');

        $header['user_phone'] = '会员联系电话';
        $header['remark']     = '备注 (可为空)';

        $top  = join(',', $header);

        echo (mb_convert_encoding($top, "gbk", "UTF-8"));
        exit;

    }

    //  修改会员池信息
    public function editClient ()
    {
        if (!IS_POST) {
            $this -> client = M('business_group') -> where(['id' => I('id/d', '')])
                                                  -> field('id, user_phone, status, remark')
                                                  -> find();
            $this -> display('edit_client');

        }else{
            $data = I('post.', '');

            $condition['user_phone'] = $data['user_phone'];
            $condition['id']         = array('neq' => $data['id']);

            $user_phone = M('business_group') -> where($condition) -> find();

            if ($user_phone) $this -> error('手机号重复', U('user/editClient', array('id' => I('id/d', ''))));

            unset($data['id']);

            $result = M('business_group') -> where(['id' => I('id/d', '0')])
                                          -> data($data)
                                          -> save();

            if ($result) {
                $this -> success('操作成功', U('user/clerkList'));
            }else{
                $this -> error('操作失败', U('user/editClient', array('id' => I('id/d', ''))));
            }
        }

    }

    //   ajax  修改会员池
    public function ajaxClient ()
    {
        $status          = trim(I('post.edit_status', ''));
        $condition['id'] = trim(I('post.id/d', ''));

        if ($condition) {

            if ($status) {

                if ($status == '公有') {
                    $editStatus['status']      = '4';
                    $editStatus['business_id'] = $_SESSION['user_auth']['id'];
                }else{
                    $editStatus['status'] = '1';
                }

                $result = M('business_group') -> where($condition)
                                              -> data($editStatus)
                                              -> save();

                if ($result) {
                    $returnData['status'] = '1';
                    $returnData['msg']    = $editStatus['status'];
                }else{
                    $returnData['status'] = '0';
                }

            }else{

                $row = M('business_group') -> where($condition) -> delete();

                if($row){
                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }

/* || ======================================================================== || */
/*   店员等级管理
/*   Y    2017-06-22   完
/* || ======================================================================== || */

    public function clerkLevel ()
    {
        $business_level_model = M('business_level');

        $keyWord = I('key', '');
        // 判断搜索条件
        if ($keyWord) {
            $map['level_name'] = array('LIKE', "%{$keyWord}%");
        }else{
            $map = '';
        }

        //统计搜索结果
        $countRes = $business_level_model -> where($map) -> count();
        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($keyWord);
        // 获取结果集
        $businessLevel = $business_level_model -> where($map)
                                    -> field('id, level_name, min_section, max_section, fees, level_status')
                                    -> order('id')
                                    -> limit($page -> firstRow.','.$page -> listRows)
                                    -> select();

        $this -> assign('keyWord', $keyWord);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('clerkLevels', $businessLevel);
        $this -> display();
    }

    //  添加店员等级
    public function addClerkLevel ()
    {
        if (!IS_POST) {

            $this -> display('edit_clerk_level');

        }else{
            $data = I('post.', '');
            unset($data['id']);
            $result = M('business_level') -> data($data) -> add();

            if ($result) {
                $this -> success('操作成功', U('user/clerkLevel'));
            }else{
                $this -> error('操作失败', U('user/addClerkLevel'));
            }
        }

    }

    //  修改业务员等级
    public function editClerkLevel ()
    {
        if (!IS_POST) {
            $this -> clerkLevel = M('business_level') -> where(['id' => I('id/d', '')])
                                                      -> field('id, level_name, min_section, max_section, fees, level_status')
                                                      -> find();
            $this -> display('edit_clerk_level');

        }else{
            $data = I('post.', '');

            unset($data['id']);

            $result = M('business_level') -> where(['id' => I('id/d', '')])
                                          -> data($data)
                                          -> save();

            if ($result) {
                $this -> success('操作成功', U('user/clerkLevel'));
            }else{
                $this -> error('操作失败', U('user/editClerkLevel'));
            }
        }

    }

    //  ajax  修改会员等级
    public function ajaxBuisnessLevel ()
    {
        $status          = trim(I('post.edit_status', ''));
        $condition['id'] = trim(I('post.id/d', ''));

        if ($condition) {

            if ($status) {

                $editStatus['level_status'] = ($status == '启用') ? '0' : '1';

                $result = M('business_level') -> where($condition)
                                              -> data($editStatus)
                                              -> save();

                if ($result) {
                    $returnData['status'] = 1;
                    $returnData['msg']    = $editStatus['level_status'];
                }else{
                    $returnData['status'] = 0;
                }

            }else{

                $row = M('business_level') -> where($condition) -> delete();

                if($row){
                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }


/* || ======================================================================== || */
/*  标签管理
/*  Y    2017-06-19   完
/* || ======================================================================== || */
    public function label ()
    {
        // 实例化标签表
        $label_model = M('label');

        $keyWord = I('key', '');
        // 判断搜索条件
        if ($keyWord) {
            $map['name'] = array('LIKE', "%{$keyWord}%");
        }else{
            $map = '';
        }

        //统计搜索结果
        $countRes = $label_model -> where($map) -> count();
        // 实例化搜索类 设置分页条数 默认为10
        $page     = new \Think\Page($countRes, 10);
        // 将查询条件加入url参数中，如果有多个查询条件则可以遍历I()，对 $page -> parameter 进行赋值
        $page -> parameter['key'] = urlencode($key);
        // 获取结果集
        $labels = $label_model -> field('id, name, add_time, remarks, status')
                               -> where($map)
                               -> order('id')
                               -> limit($page -> firstRow.','.$page -> listRows)
                               -> select();

        $this -> assign('keyWord', $keyWord);  //传递搜索词
        $this -> assign('page', $page -> show()); // 赋值分页输出
        $this -> assign('label', $labels);
        $this -> display();
    }

    // 添加标签
    public function addLabel ()
    {
        if (!IS_POST) {

            $this -> display('edit_label');

        }else{

            $label             = I('post.');
            $label['add_time'] = time();
            $label['remarks']  = trim(I('post.remarks'));

            $result = M('label') -> data($label) -> add();

            if ($result) {
                $this -> success('添加成功', 'label', 3);
            }else{
                $this -> success('添加失败', 'addLabel', 3);
            }
        }
    }

    // 编辑标签
    public function editLabel ()
    {
        if (!IS_POST) {

            $condition['id'] = $id;
            $label           = M('label') -> where($condition) -> find();

            $this -> assign('type', 'update');
            $this -> assign('label', $label);
            $this -> display('edit_label');

        }else{

            $condition['id'] = I('post.id/d', '');

            if (empty($condition['id']))
            {
                $this -> error('ID不能为空', "editLabel", 3);
                exit;
            }

            $label             = I('post.');

            $label['add_time'] = time();
            $label['remarks']  = trim(I('post.remarks'));

            unset($label['id']);

            $result = M('label') -> where($condition)
                                 -> data($label)
                                 -> save();

            if ($result) {
                $this -> success('修改成功', 'label', 3);
            }else{
                $this -> error('修改失败', "editLabel/id/{$condition['id']}", 3);
            }
        }
    }

    // 删除标签
    public function delLabel ()
    {
        $lab_id = I('get.id', '');

        if (!empty($lab_id)) {
            $row = M('label') -> where(array('id' => $lab_id)) -> delete();

            if ($row) {
                $this -> success('删除标签成功');
            }else{
                $this -> error('操作失败');
            }
        }else{
            $this -> error('操作失败');
        }
    }

    // Ajax修改状态、删除
    public function ajaxLabel ()
    {
        $status       = trim(I('post.lab_status', ''));
        $lab_id['id'] = trim(I('post.lab_id/d', ''));

        if ($lab_id) {

            if ($status) {
                //  修改状态
                $editStatus['status'] = ($status == '启用') ? '0' : '1';

                $result = M('label') -> where($lab_id)
                                     -> data($editStatus)
                                     -> save();

                if ($result) {
                    $returnData['status'] = 1;
                    $returnData['msg']    = $editStatus['status'];
                }else{
                    $returnData['status'] = 0;
                }

            }else{
                //  删除操作
                $row = M('label') -> where($lab_id) -> delete();

                if($row){
                    $returnData['status'] = '1';
                }else{
                    $returnData['status'] = '0';
                }
            }

        }else{
            $returnData['status'] = '0';
        }

        $this -> ajaxReturn($returnData);
    }

    /**
     * [editPasswd 管理员自己修改密码]
     * @return [type] []
     */
    public function editPasswd ()
    {
        $map['id'] = $_SESSION['user_auth']['id'];
        if (!$_POST) {
            $this -> userInfo = M('agent_busi') -> where($map) -> field('id, agent_name') -> find();
            $this -> display();
        }else{

            if (empty(I('post.password')) || (strlen(trim(I('post.password'))) < '6')) {
                $this -> error('新密码长度必须大于6位', U('login/editPasswd', $map));
            }

            $result = M('agent_busi') -> where($map)
                                      -> data(['password' => md5(trim(I('post.password')))])
                                      -> save();
            if ($result) {
                session('user_auth', null);
                session('user_auth_sign', null);
                session('[destroy]');
                $this -> success('密码修改成功', U('login/index'));
            }else{
                $this -> error('密码修改失败', U('login/editPasswd', $map));
            }
        }

    }

    /**
     * [creatURL 创建推广连接]
     * @Author   zhouyu
     * @DateTime 2017-09-08T12:16:26+0800
     * @return   [type]                   [description]
     */
    public function creatURL()
    {
       //找到自身id
       
       //拼接url 
       
       $agent_id = $_SESSION['user_auth']['id'];
       $code = M('Invite_code')->where(array('user_from'=>'1','per_id'=>$agent_id))->find();
       if(!$code){
        $save = array();
        $save['user_from'] = '1';
        $save['per_id'] = $agent_id;
        $save['code'] = md5('agent_busi'.$agent_id);        
         M('Invite_code')->where(array('user_from'=>'1','per_id'=>$agent_id))->add($save);
         $code = M('Invite_code')->where(array('user_from'=>'1','per_id'=>$agent_id))->find();
       }
       $url =  'http://'.md5(time()+floor(rand(0,1000))).'.ag.99caihong.net/registFrom/'.$code['code'];
       $level=3;
       $size=4;
       Vendor('phpqrcode.phpqrcode');
       $errorCorrectionLevel =intval($level) ;//容错级别 
       $matrixPointSize = intval($size);//生成图片大小 
       //生成二维码图片 
       $object = new \QRcode();
       $path = "qrcode/";            // 生成的文件名            //
       $fileName = $path.'2'.'.png';          //文件名也可以考虑用生成一个日期变量
       $object->png($url, $fileName, $errorCorrectionLevel, $matrixPointSize, 2);  
       
       $bg = imagecreatefrompng('qrcode/logo.png');
       $qrcode = imagecreatefrompng('qrcode/2.png');
       imagecopyresampled($bg, $qrcode, 241, 528, 0, 0, 241, 241, imagesx($qrcode), imagesy($qrcode));
       $filepath = 'qrcode/'.$code['code'].'.png';
       // $imgpath = trim('/qrcodes/' . $res->code. '.png');
       imagepng($bg, $filepath);    

       $this->assign('url',$url);
       $this->assign('filepath',$filepath);
       $this->display();   
    }

    public function editfandian()
    {
        $id = I('get.id');

        $fandian = M('User_rebate')->getByUser_id($id);
        if(!$fandian){
        	M('User_rebate')->add(array('user_id'=>$id));
        	$fandian = M('User_rebate')->getByUser_id($id);
        } 
        $this->assign('fandian',$fandian); 
        $this->assign('id',$id);
        $this->display();  
    }
    public function editfandianOP()
    {
        $id    = trim(I('post.id'));
        $zc    = trim(I('post.ZC'));
        $lc    = trim(I('post.LC'));
        $r14   = trim(I('post.R14'));
        $r9    = trim(I('post.R9'));
        $ks    = trim(I('post.KS'));
        $zsyxw = trim(I('post.ZSYXW'));
        $dlt   = trim(I('post.DLT'));

        // dump($r9);die;

        $res = M('User_rebate')->where(array('user_id'=>$id))->save(array('rebate_e'=>$zc,'rebate_f'=>$lc,'rebate_i'=>$r14,'rebate_j'=>$r9,'rebate_k'=>$ks,'rebate_a'=>$zsyxw,'rebate_h'=>$dlt));
        if($res){
            //修改成功
            $this->success('返点修改成功!',U('User/editfandian',array('id'=>$id)));
        }else{
            $this->error('返点修改失败!');
        }
        
    }
    public function seeSalesmanURL()
    {
    	$agent_id = I('get.id');

		$code = M('Invite_code')->where(array('user_from'=>'1','per_id'=>$agent_id))->find();
       if(!$code){
        $save = array();
        $save['user_from'] = '1';
        $save['per_id'] = $agent_id;
        $save['code'] = md5('agent_busi'.$agent_id);        
         M('Invite_code')->where(array('user_from'=>'1','per_id'=>$agent_id))->add($save);
         $code = M('Invite_code')->where(array('user_from'=>'1','per_id'=>$agent_id))->find();
       }
       $url = 'http://'.md5(time()+floor(rand(0,1000))).'.ag.99caihong.net/registFrom/'.$code['code'];
       $level=3;
       $size=4;
       Vendor('phpqrcode.phpqrcode');
       $errorCorrectionLevel =intval($level) ;//容错级别 
       $matrixPointSize = intval($size);//生成图片大小 
       //生成二维码图片 
       $object = new \QRcode();
       $path = "qrcode/";            // 生成的文件名            //
       $fileName = $path.'2'.'.png';          //文件名也可以考虑用生成一个日期变量
       $object->png($url, $fileName, $errorCorrectionLevel, $matrixPointSize, 2);  
       
       $bg = imagecreatefrompng('qrcode/logo.png');
       $qrcode = imagecreatefrompng('qrcode/2.png');
       imagecopyresampled($bg, $qrcode, 241, 528, 0, 0, 241, 241, imagesx($qrcode), imagesy($qrcode));
       $filepath = 'qrcode/'.$code['code'].'.png';
       // $imgpath = trim('/qrcodes/' . $res->code. '.png');
       imagepng($bg, $filepath);    

       $this->assign('url',$url);
       $this->assign('filepath',$filepath);
       $this->display();       	
    }

    /**
     * [addjiafans 添加假粉丝]
     * @Author   zhouyu
     * @DateTime 2017-11-20T16:19:50+0800
     * @return   [type]                   [description]
     */
    public function addjiafans()
    {
        $model = M('User_attr_jia');
        if(IS_POST){
            $data = I('post.');
            
            $is_find = $model->where(['user_id'=>$data['id']])->find();
            if($is_find){
                $res = $model->where(['user_id'=>$data['id']])->save($data);

            }else{
                $temp = $data;
                $temp['user_id'] = $data['id'];
                $res = $model->add($temp);
            }
            if($res){
                $this->success('修改成功!');
            }else{
                $this->error('修改失败!');
            }
        }else{
            $id = I('get.id');
            $jia = $model->where(['user_id'=>$id])->find();
            // dump($data);die;
            $this->assign('jia',$jia);
            $this->assign('id',$id);
            $this->display();
        }
        
    }

    public function goldListView()
    {
        if(IS_POST){
            $user_name = I('post.user_name');
            $model = M('User_info');

            $find = $model->field('id')->where(['user_name'=>$user_name])->find();
            if($find){
                $count = M('User_gold')->count();
                $res = M('User_gold')->field('sort')->where(['user_id'=>$find['id']])->find();
                if($res){

                    $this->assign('info','添加失败!该用户已存在,排名为'.$res['sort']);

                }else{
                    M('User_gold')->add(['user_id'=>$find['id'],'sort'=>$count+1]);

                    $this->assign('info','添加成功!该用户排名为'.($count+1));
                }
                

            }else{
                $this->assign('info','该用户不存在!');
            }

            
        }

        $countRes = M('User_gold')->count();
        $page     = new \Think\Page($countRes, 10);
        $gold_data     = M('User_gold')
                        -> alias('gd')
                        -> field('gd.sort,gd.user_id,ui.user_name')
                        -> join('left join tp_user_info as ui on ui.id = gd.user_id')
                        -> order('gd.sort asc')
                        -> limit($page -> firstRow, $page -> listRows)
                        
                        -> select();

        $this->assign('gold_data',$gold_data);
        $this->assign('page',$page->show());
        $this->display();
        
    }

    public function goldListDelete()
    {
        $user_id = I('get.user_id');
        
        $res     = M('User_gold')->where(['user_id'=>$user_id])->delete();

        if($res){
            $this->success('删除成功!');
        }else{
            $this->error('删除失败!');
        }

    }
    public function changeGoldSort()
    {
        $user_id = I('post.user_id');
        $sort    = I('post.sort');

        $res = M('User_gold')->where(['user_id'=>$user_id])->save(['sort'=>$sort]);

        if($res){

            $data['code'] = '0';
            $data['message'] = '修改排名成功!';
            echo json_encode($data);

        }else{

            $data['code'] = '1';
            $data['message'] = '修改排名失败!';
            echo json_encode($data);

        }
    }

    public function changeUserIndex(){
        $this->display('changeUserIndex');
    }

    public function changeUser()
    {
        //实例化模型
        $user = M('user_and_user');
        //获取数据
        $change_data = I('post.');
        $uid = $change_data['uid'];
        $cid = $change_data['cid'];
        //判断
       if(empty($uid)||empty($cid)){
           $this->display('changeUserIndex');
       }else{
           //逻辑
           if($cid == 0){
               //如果晋升顶级代理，pass值直接更新为0
               $user->where(['user_id'=>$uid])->data(['pid'=>0])->save();
               //更新子级的pass值
               $this->getPass($uid,0);
           }else {
               //查询更换用户的pass值
               $change_user = $user->where(['user_id' => $cid])->find();
               $change_pass = $change_user['pass'];

               //拆分pass值，保证用户不更换成子孙
               $new_pass = explode("-", $change_pass);
               if (in_array($uid, $new_pass)) {
                   $this->error('更新失败');
               } else {
                   //更新本级pid
                   $data['pid'] = $cid;
                   //更新本级的pid值
                   $user->where(['user_id' => $uid])->data($data)->save();
                   //更新本级和子级的pass值
                   $this->getPass($cid, $change_pass);
                   $this->success('更新成功');
               }
           }
       }
    }

    public function getPass($cid,$pass)
    {
        //查询要更新的子级
        $result = M('user_and_user')->where(['pid' => $cid])->field('user_id')->select();
        foreach ($result as $value) {
            //拼接新pass
            $joint_pass = "$pass-$cid";
            //更新pass值
            $current_pass = $joint_pass;
            M('user_and_user')->where(['user_id' => $value['user_id']])->save(['pass' => $current_pass]);
            $this->getPass($value['user_id'], $current_pass);
        }
    }

}