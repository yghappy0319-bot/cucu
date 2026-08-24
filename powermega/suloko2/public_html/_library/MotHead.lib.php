<?php
///Version 0.11
/*
문자발송 라이브러리-가급적 수정 하지 마세요.
*/
class MotHead_Send{


    public function MotHeadServerConn($rd,$is_report){

                        $g="";
                        $url="http://sock.smsnori.com/ext/socket.php";
                        $timeout=15;



                        $Snd=array();

                        if ($is_report=="report"){

                            $Snd["REPORT"]=$is_report;
                            $Snd["U_CODE"]=$rd;

                        }else{

                            $Snd=$rd;

                        }

                         $post_rd=array();

                            foreach ($Snd as $k=>$v){

                                if ($v!=""){
                                    $post_rd[$k]=base64_encode($v);
                                }

                            }

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_rd);
                        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                        ///curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

                        $g = curl_exec($ch);



                        $ch_info=curl_getinfo($ch);

                        $ch_res_code=$ch_info['http_code'];
                        $ch_res_url=$ch_info['url'];
                        $ch_req_count=$ch_info['redirect_count'];
                        curl_close($ch);


                        $r=array();
                        $jr=array();

                        $r['status']=false;
                        $r['result']=0;
                        $r['code']="";
                        $r['msg']="";
                        $r['count']=0;

                        $jr['status']=false;
                        $jr['result']=0;
                        $jr['msg']="";
                        $jr['process']=0;
                        $jr['count']=0;
                        $jr['ok_count']=0;
                        $jr['fail_count']=0;



                        if ($ch_res_code!="200"){

                            $r['status']=false;

                        }else{

                            $r['status']=true;
                            $jr['status']=true;


                            $r_doc=explode(":",$g);

                            if ($r_doc[0]=="ok"){

                                $r['result']=1;
                                $r['count']=isset($r_doc[1]) ? $r_doc[1] : 0;
                                $r['code']=isset($r_doc[2]) ? $r_doc[2] : "";
                                $r['msg']="";
                                $r['U_VAL']=isset($r_doc[5]) ? $r_doc[5] : "";


                                $jr['result']=1;
                                $jr['msg']="";
                                $jr['process']=isset($r_doc[1]) ? $r_doc[1] : "";
                                $jr['count']=isset($r_doc[2]) ? $r_doc[2] : 0;
                                $jr['ok_count']=isset($r_doc[3]) ? $r_doc[3] : 0;
                                $jr['fail_count']=isset($r_doc[4]) ? $r_doc[4] : 0;

                            }else{
                                $r['msg']=$r_doc[1];
                                $jr['msg']=$r_doc[1];
                            }

                        }

                        if ($is_report=="report"){
                            return $jr;
                        }else{
                            return $r;
                        }
    }


    public function Send($rd){

                   return $this->MotHeadServerConn($rd,"");

        }

    public function Report($rd){

                   return $this->MotHeadServerConn($rd,"report");

        }

}
?>
