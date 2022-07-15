<?php

class MultiHttpRequest
{
    public $requests = [];

    /**
     * 设置请求url
     *
     * @param $requests
     * @return $this
     */
    public function setRequests($requests) {
        $this->requests = $requests;
        return $this;
    }

    /**
     * 发送请求
     *
     * @return array|false
     */
    public function request()
    {
        if(!is_array($this->requests) or count($this->requests) == 0){
            return false;
        }

        $curl   = $response = [];
        $handle = curl_multi_init();
        foreach($this->requests as $k => $v){
            $url      = isset($v['url']) ? $v['url'] : '';
            $postData = isset($v['postData']) ? $v['postData'] : [];
            $header   = isset($v['header']) ? $v['header'] : [];
            $timeOut  = isset($v['timeOut']) ? $v['timeOut'] : 1;
            $proxy    = isset($v['proxy']) ? $v['proxy'] : '';
            $curl[$k] = $this->buildCurlObject($url, $postData, $header, $timeOut, $proxy);

            curl_multi_add_handle($handle, $curl[$k]);
        }

        $this->execHandle($handle);
        foreach ($this->requests as $key => $val){
            $response[$key] =  curl_multi_getcontent($curl[$key]);

            curl_multi_remove_handle($handle, $curl[$key]);
            curl_close($curl[$key]);
        }

        curl_multi_close($handle);

        return $response;
    }

    /**
     * 构造请求
     *
     * @param $url
     * @param $postData
     * @param $header
     * @param $timeOut
     * @param $proxy
     * @return false|resource
     */
    private function buildCurlObject($url, $postData, $header, $timeOut, $proxy) {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, (int)$timeOut);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        // 配置代理
        if (!empty($proxy))
            curl_setopt($curl, CURLOPT_PROXY, $proxy);

        // 合并请求头部信息
        if(!empty($header))
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // 是否是post请求
        if(!empty($postData) && is_array($postData)){
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS,  http_build_query($postData));
        }

        // 是否是https
        if(stripos($url,'https') === 0){
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        return $curl;
    }

    /**
     * 执行批处理句柄
     *
     * @param $handle
     * @return void
     */
    private function execHandle($handle)
    {
        $active = true;
        $mrc = CURLM_OK;
        while ($active && $mrc == CURLM_OK) {
            do {
                $mrc = curl_multi_exec($handle, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            if (curl_multi_select($handle) == -1) {
                usleep(100);
            }
        }
    }
}
