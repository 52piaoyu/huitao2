<?php
class HtPowerController extends HtController
{
    /**
     * [setPower 设置某个后台用户的权限]
     */
    public function setpower()
    {
        $n = M('htnode')->select();
        D($n);
    }
    public function setpower2()
    {
        /**
         * 如果只设置了username，那么是修改权限
        */
        if (!empty($_POST['username'])&&empty($_POST['password'])) {
            $id= M('htuser')->where(['username' => ['=',$_POST['username']]])->field('id')->select('single')['id'];
            if ($id) {
                if ($id==1) {
                    info("超级管理员权限禁止修改", -1);
                }
                //清除所有权限
                M('htrole')->where(['htUser_id' => ['=',$id]])->save();
                $arr = explode(",", $_POST['htNode_id']);
                $myarr=[];
                for ($i=0;$i<count($arr);$i++) {
                    $myarr[$i]['htUser_id']=$id;
                    $myarr[$i]['htNode_id']=$arr[$i];
                }

                /**
                 * 赋予已被清空的基础权限
                */
                M('htrole')->add(['htUser_id' => $id, 'htNode_id' => '201']);
                M('htrole')->add(['htUser_id' => $id, 'htNode_id' => '202']);
                $data=M('htrole')->batchAdd($myarr);
                $data?info("权限修改成功", 1):info("权限修改失败", -3);
            } else {
                info("请输入正确的用户名", -1);
            }
        }
        /**
         * 如果设置了username，password那么创建用户，并赋予权限(基础权限：个人中心页,退出登录的权限)
        */
        else {
            if (!empty($_POST['username'])&&!empty($_POST['password'])) {
                if (M('htuser')->where(['username' => ['=',$_POST['username']]])->select()) {
                    info("用户名已存在，请重新设置", -1);
                } else {
                    $id= M('htuser')->add(['username' => $_POST['username'], 'password' => $_POST['password']]);
                    M('htrole')->add(['htUser_id' => $id, 'htNode_id' => '201']);
                    M('htrole')->add(['htUser_id' => $id, 'htNode_id' => '202']);
                    $arr2 = explode(",", $_POST['htNode_id']);
                    $myarr2=[];
                    for ($i=0;$i<count($arr2);$i++) {
                        $myarr2[$i]['htUser_id']=$id;
                        $myarr2[$i]['htNode_id']=$arr2[$i];
                    }
                    $data2=M('htrole')->batchAdd($myarr2);
                    $data2?info("用户添加成功,用户id为".$id, 1):info("用户或用户权限添加失败", -3);
                }
            }
        }
    }    /**
     * [getPower 查看某个后台用户所拥有的权限]
     */
    public function getpower()
    {
        if (!empty($_POST['username'])) {
            $id= M('htuser')->where(['username' => ['=',$_POST['username']]])->field('id')->select('single')['id'];
            if ($id) {
                $n = implode(',', array_column((M('htrole')->field('htNode_id')->where(['htUser_id' => ['=',$id]])->select()), "htNode_id"));
                $n = M('htnode')->where("id in({$n})")->select();
                info('OK', 1, $n);
            } else {
                info("不存在的用户名", -1);
            }
        } elseif (!empty($_SESSION['user']['id'])) {
            info("ok", 1);
        } else {
        }
    }
    /**
     * iniPower打开每个页面的时候获取权限列表，根据权限列表显示菜单项
     */
    public function inipower()
    {
        if (!empty($_SESSION['user']['id'])) {
            if (M('htrole')->where(['htUser_id' => ['=', $_SESSION['user']['id']]])->select()) {
                $n = implode(',', array_column((M('htrole')->field('htNode_id')->where(['htUser_id' => ['=', $_SESSION['user']['id']]])->select()), "htNode_id"));
                $n = M('htnode')->where("id in({$n})")->select();
                info('OK', 1, $n);
            }
        }
        info("获取用户初始权限失败", -1);
    }
}
