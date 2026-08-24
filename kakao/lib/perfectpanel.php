<?php
class Api
{

    public function addPayment($_api_data, $username, $amount, $details) {
        return json_decode($this->connect(array(
            'key' => $_api_data[1],
            'action' => 'addpayment',
            'username' => $username,
            'amount' => $amount,
            'details' => $details,
            'affiliate_commission' => 1
        ),'',$_api_data));
    }


    private function connect($data, $jsonBody = false, $_api_data) {
        $ch = curl_init($_api_data[0]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0, PrivateApi)');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        if ($jsonBody) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        }

        $result = curl_exec($ch);
        if (curl_errno($ch) != 0 && empty($result)) {
            $result = false;
        }
        curl_close($ch);
        return $result;
    }
}
