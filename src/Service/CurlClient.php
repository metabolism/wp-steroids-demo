<?php


use Psr\Log\LoggerInterface;

class CurlClient {

	private $client;
	private $baseUrl;
	private $authorization;
	private $logger=[];
	private $contentType;
	private $errorKey;

	public function __construct(){

		$this->client = new \GuzzleHttp\Client();

		$this->errorKey = 'message';
		$this->contentType = 'application/json';

        add_action( 'admin_notices', [$this, 'showLogs'] );
    }

	public function showLogs(){

        if( !empty($this->logger) )
            echo '<div class="notice notice-error"><p><b>Zoom error:</b></p><pre>'.implode('\n', $this->logger).'</pre></div>';
	}

	public function getLastError(){

        return end($this->logger);
	}

	/**
	 * @param $baseUrl
	 */
	public function setBaseUrl($baseUrl){

		$this->baseUrl = $baseUrl;
	}

	/**
	 * @param $key
	 */
	public function setErrorKey($key){

		$this->errorKey = $key;
	}

	/**
	 * @param $contentType
	 */
	public function setContentType($contentType){

		$this->contentType = $contentType;
	}

	/**
	 * @param $authorization
	 */
	public function setAuthorization($authorization){

		$this->authorization = $authorization;
	}

	/**
	 * @param $url
	 * @param array $params
	 * @return string
	 */
	public function getUrl($url, $params=[]){

		return $this->baseUrl.$url.(!empty($params)?'?'.http_build_query($params):'');
	}

	/**
	 * @param array $headers
	 * @return array
	 */
	private function getHeaders($headers=[]){

        if( !($headers['Content-Type']??false) && !empty($this->contentType) )
            $headers['Content-Type'] = $this->contentType;

        if(  !($headers['Authorization']??false) && !empty($this->authorization) )
            $headers['Authorization'] = $this->authorization;

        return $headers;
    }

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function get($path='', $params=[], $headers=[]){

		try {
			return $this->request('GET', $path, $params, $headers);
		}
		catch (Throwable $t) {

            $this->logger[] = $t->getMessage();
        }

        return false;
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function post($path='', $params=[], $headers=[]){

		try{
			return $this->request('POST', $path, $params, $headers);
		}
		catch (Throwable $t) {

            $this->logger[] = $t->getMessage();
		}

        return false;
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function delete($path='', $params=[], $headers=[]){

		try{
			return $this->request('DELETE', $path, $params, $headers);
		}
		catch (Throwable $t) {

            $this->logger[] = $t->getMessage();
		}

        return false;
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function put($path='', $params=[], $headers=[]){

		try{
			return $this->request('PUT', $path, $params, $headers);
		}
		catch (Throwable $t) {

            $this->logger[] = $t->getMessage();
		}

        return false;
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function patch($path='', $params=[], $headers=[]){

		try {
			return $this->request('PATCH', $path, $params, $headers);
		}
		catch (Throwable $t) {

            $this->logger[] = $t->getMessage();
		}

        return false;
	}

    /**
     * @param $method
     * @param string $path
     * @param array $params
     * @param array $headers
     * @return array|bool|SimpleXMLElement
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
	public function request($method, $path='', $params=[], $headers=[]){

		$requestHeaders = $this->getHeaders($headers);

		$options = ['headers' => $requestHeaders];

		if( $method == 'GET' ){

			$options['query'] = $params;
		}
		else{

			if( ($requestHeaders['Content-Type']??'') == 'application/json' )
				$options['json'] = $params;
			elseif( ($requestHeaders['Content-Type']??'') == 'application/x-www-form-urlencoded' )
                $options['form_params'] = $params;
            else
				$options['body'] = $params;
		}

        $url = substr($path, 0, 4) === 'http' ? $path : $this->baseUrl.$path;

		$response = $this->client->request($method, $url, $options);

		$statusCode = $response->getStatusCode();
		$responseHeaders = $response->getHeaders(false);

		$responseContentType = trim(strtolower($responseHeaders['content-type'][0]??'application/json'));

		if(str_contains($responseContentType, 'json')){

			$value = $response->getBody();

			if( $statusCode >= 200 && $statusCode < 300)
				return json_decode($value->getContents(), true);

			$error = $value[$this->errorKey]??'Service error';
			if( is_array($error) ) $error = json_encode($error);

			throw new Exception($error, $statusCode);

		}
		elseif(str_contains($responseContentType, 'xml')){

            $value = $response->getBody();

			if( !empty($body) )
				$value = @simplexml_load_string($value->getContents());

			if( $statusCode >= 200 && $statusCode < 300)
				return $value;

			$errorKey = $this->errorKey;
			$error = $value->$errorKey??'Service error';

			if( is_array($error) ) $error = json_encode($error);

			throw new Exception($error, $statusCode);
		}
		else{

			$value = $response->getBody();

			if( $statusCode >= 200 && $statusCode < 300)
				return $value->getContents();

			throw new Exception('Service error', $statusCode);
		}
	}

	/**
	 * @param string $path
	 * @param array $formFields
	 * @return array|bool
	 * @throws Exception
	 */
	public function postForm($path='', $formFields=[]){

		try {

			return $this->request('POST', $path, $formFields, ['Content-Type'=>'multipart/form-data']);
		}
		catch (Throwable $t) {

			throw new Exception($t->getMessage(), $t->getCode());
		}
	}
}