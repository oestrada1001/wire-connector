<?php

namespace includes\WC_MailChimp;

include 'Batch.php';

/**
 * Super-simple, minimum abstraction MailChimp API v3 wrapper
 * MailChimp API v3: http://developer.mailchimp.com
 * This wrapper: https://github.com/drewm/mailchimp-api
 *
 * @author  Drew McLellan <drew.mclellan@gmail.com>
 * @version 2.5
 */
class MailChimp
{
    private $credentials;
    private $api_key;
    private $api_endpoint;

    protected $wpChimpFramework;

    protected $list;
    protected $merge_field_data;

    const TIMEOUT = 10;

    /*  SSL Verification
        Read before disabling:
        http://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/
    */
    public $verify_ssl = true;

    private $request_successful = false;
    private $last_error         = '';
    private $last_response      = array();
    private $last_request       = array();

    /**
     * Create a new instance
     *
     * @param string $api_key      Your MailChimp API key
     * @param string $api_endpoint Optional custom API endpoint
     *
     * @throws \Exception
     */
    public function __construct($api_key, $api_endpoint = null)
    {

        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }


        $this->api_key = $api_key;

        if ($api_endpoint === null) {
            if (strpos($this->api_key, '-') === false) {
                throw new \Exception("Invalid MailChimp API key supplied.");
            }
            list(, $data_center) = explode('-', $this->api_key);
            $this->api_endpoint = str_replace('<dc>', $data_center, $this->api_endpoint);
        } else {
            $this->api_endpoint = $api_endpoint;
        }

        $this->last_response = array('headers' => null, 'body' => null);
    }

    /**
     * Create a new instance of a Batch request. Optionally with the ID of an existing batch.
     *
     * @param string $batch_id Optional ID of an existing batch, if you need to check its status for example.
     *
     * @return Batch            New Batch object.
     */
    public function new_batch($batch_id = null)
    {
        return new Batch($this, $batch_id);
    }

    /**
     * @return string The url to the API endpoint
     */
    public function getApiEndpoint()
    {
        return $this->api_endpoint;
    }


    /**
     * Convert an email address into a 'subscriber hash' for identifying the subscriber in a method URL
     *
     * @param   string $email The subscriber's email address
     *
     * @return  string          Hashed version of the input
     */
    public function subscriberHash($email)
    {
        return md5(strtolower($email));
    }

    /**
     * Was the last request successful?
     *
     * @return bool  True for success, false for failure
     */
    public function success()
    {
        return $this->request_successful;
    }

    /**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     *
     * @return  string|false  describing the error
     */
    public function getLastError()
    {
        return $this->last_error ?: false;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API response.
     *
     * @return array  Assoc array with keys 'headers' and 'body'
     */
    public function getLastResponse()
    {
        return $this->last_response;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API request.
     *
     * @return array  Assoc array
     */
    public function getLastRequest()
    {
        return $this->last_request;
    }

    /**
     * Make an HTTP DELETE request - for deleting data
     *
     * @param   string $method  URL of the API request method
     * @param   array  $args    Assoc array of arguments (if any)
     * @param   int    $timeout Timeout limit for request in seconds
     *
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function delete($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('delete', $method, $args, $timeout);
    }

    /**
     * Make an HTTP GET request - for retrieving data
     *
     * @param   string $method  URL of the API request method
     * @param   array  $args    Assoc array of arguments (usually your data)
     * @param   int    $timeout Timeout limit for request in seconds
     *
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function get($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('get', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PATCH request - for performing partial updates
     *
     * @param   string $method  URL of the API request method
     * @param   array  $args    Assoc array of arguments (usually your data)
     * @param   int    $timeout Timeout limit for request in seconds
     *
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function patch($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('patch', $method, $args, $timeout);
    }

    /**
     * Make an HTTP POST request - for creating and updating items
     *
     * @param   string $method  URL of the API request method
     * @param   array  $args    Assoc array of arguments (usually your data)
     * @param   int    $timeout Timeout limit for request in seconds
     *
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function post($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('post', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PUT request - for creating new items
     *
     * @param   string $method  URL of the API request method
     * @param   array  $args    Assoc array of arguments (usually your data)
     * @param   int    $timeout Timeout limit for request in seconds
     *
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function put($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('put', $method, $args, $timeout);
    }

    /**
     * Performs the underlying HTTP request. Not very exciting.
     *
     * @param  string $http_verb The HTTP verb to use: get, post, put, patch, delete
     * @param  string $method    The API method to be called
     * @param  array  $args      Assoc array of parameters to be passed
     * @param int     $timeout
     *
     * @return array|false Assoc array of decoded result
     */
    private function makeRequest($http_verb, $method, $args = array(), $timeout = self::TIMEOUT)
    {
        $url = $this->api_endpoint . '/' . $method;

        $response = $this->prepareStateForRequest($http_verb, $method, $url, $timeout);

        $httpHeader = array(
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Authorization: apikey ' . $this->api_key
        );

        if (isset($args["language"])) {
            $httpHeader[] = "Accept-Language: " . $args["language"];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DrewM/MailChimp-API/3.0 (github.com/drewm/mailchimp-api)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        switch ($http_verb) {
            case 'post':
                curl_setopt($ch, CURLOPT_POST, true);
                $this->attachRequestPayload($ch, $args);
                break;

            case 'get':
                $query = http_build_query($args, '', '&');
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
                break;

            case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'patch':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                $this->attachRequestPayload($ch, $args);
                break;

            case 'put':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                $this->attachRequestPayload($ch, $args);
                break;
        }

        $responseContent     = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);
        $response            = $this->setResponseState($response, $responseContent, $ch);
        $formattedResponse   = $this->formatResponse($response);

        curl_close($ch);

        $isSuccess = $this->determineSuccess($response, $formattedResponse, $timeout);

        return is_array($formattedResponse) ? $formattedResponse : $isSuccess;
    }

    /**
     * @param string  $http_verb
     * @param string  $method
     * @param string  $url
     * @param integer $timeout
     *
     * @return array
     */
    private function prepareStateForRequest($http_verb, $method, $url, $timeout)
    {
        $this->last_error = '';

        $this->request_successful = false;

        $this->last_response = array(
            'headers'     => null, // array of details from curl_getinfo()
            'httpHeaders' => null, // array of HTTP headers
            'body'        => null // content of the response
        );

        $this->last_request = array(
            'method'  => $http_verb,
            'path'    => $method,
            'url'     => $url,
            'body'    => '',
            'timeout' => $timeout,
        );

        return $this->last_response;
    }

    /**
     * Get the HTTP headers as an array of header-name => header-value pairs.
     *
     * The "Link" header is parsed into an associative array based on the
     * rel names it contains. The original value is available under
     * the "_raw" key.
     *
     * @param string $headersAsString
     *
     * @return array
     */
    private function getHeadersAsArray($headersAsString)
    {
        $headers = array();

        foreach (explode("\r\n", $headersAsString) as $i => $line) {
            if ($i === 0) { // HTTP code
                continue;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            list($key, $value) = explode(': ', $line);

            if ($key == 'Link') {
                $value = array_merge(
                    array('_raw' => $value),
                    $this->getLinkHeaderAsArray($value)
                );
            }

            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Extract all rel => URL pairs from the provided Link header value
     *
     * Mailchimp only implements the URI reference and relation type from
     * RFC 5988, so the value of the header is something like this:
     *
     * 'https://us13.api.mailchimp.com/schema/3.0/Lists/Instance.json; rel="describedBy",
     * <https://us13.admin.mailchimp.com/lists/members/?id=XXXX>; rel="dashboard"'
     *
     * @param string $linkHeaderAsString
     *
     * @return array
     */
    private function getLinkHeaderAsArray($linkHeaderAsString)
    {
        $urls = array();

        if (preg_match_all('/<(.*?)>\s*;\s*rel="(.*?)"\s*/', $linkHeaderAsString, $matches)) {
            foreach ($matches[2] as $i => $relName) {
                $urls[$relName] = $matches[1][$i];
            }
        }

        return $urls;
    }

    /**
     * Encode the data and attach it to the request
     *
     * @param   resource $ch   cURL session handle, used by reference
     * @param   array    $data Assoc array of data to attach
     */
    private function attachRequestPayload(&$ch, $data)
    {
        $encoded                    = json_encode($data);
        $this->last_request['body'] = $encoded;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }

    /**
     * Decode the response and format any error messages for debugging
     *
     * @param array $response The response from the curl request
     *
     * @return array|false    The JSON decoded into an array
     */
    private function formatResponse($response)
    {
        $this->last_response = $response;

        if (!empty($response['body'])) {
            return json_decode($response['body'], true);
        }

        return false;
    }

    /**
     * Do post-request formatting and setting state from the response
     *
     * @param array    $response        The response from the curl request
     * @param string   $responseContent The body of the response from the curl request
     * @param resource $ch              The curl resource
     *
     * @return array    The modified response
     */
    private function setResponseState($response, $responseContent, $ch)
    {
        if ($responseContent === false) {
            $this->last_error = curl_error($ch);
        } else {

            $headerSize = $response['headers']['header_size'];

            $response['httpHeaders'] = $this->getHeadersAsArray(substr($responseContent, 0, $headerSize));
            $response['body']        = substr($responseContent, $headerSize);

            if (isset($response['headers']['request_header'])) {
                $this->last_request['headers'] = $response['headers']['request_header'];
            }
        }

        return $response;
    }

    /**
     * Check if the response was successful or a failure. If it failed, store the error.
     *
     * @param array       $response          The response from the curl request
     * @param array|false $formattedResponse The response body payload from the curl request
     * @param int         $timeout           The timeout supplied to the curl request.
     *
     * @return bool     If the request was successful
     */
    private function determineSuccess($response, $formattedResponse, $timeout)
    {
        $status = $this->findHTTPStatus($response, $formattedResponse);

        if ($status >= 200 && $status <= 299) {
            $this->request_successful = true;
            return true;
        }

        if (isset($formattedResponse['detail'])) {
            $this->last_error = sprintf('%d: %s', $formattedResponse['status'], $formattedResponse['detail']);
            return false;
        }

        if ($timeout > 0 && $response['headers'] && $response['headers']['total_time'] >= $timeout) {
            $this->last_error = sprintf('Request timed out after %f seconds.', $response['headers']['total_time']);
            return false;
        }

        $this->last_error = 'Unknown error, call getLastResponse() to find out what happened.';
        return false;
    }

    /**
     * Find the HTTP status code from the headers or API response body
     *
     * @param array       $response          The response from the curl request
     * @param array|false $formattedResponse The response body payload from the curl request
     *
     * @return int  HTTP status code
     */
    private function findHTTPStatus($response, $formattedResponse)
    {
        if (!empty($response['headers']) && isset($response['headers']['http_code'])) {
            return (int)$response['headers']['http_code'];
        }

        if (!empty($response['body']) && isset($formattedResponse['status'])) {
            return (int)$formattedResponse['status'];
        }

        return 418;
    }

	/**
	 * Public interface that runs getListsIdentification
	 *
	 * @param string    $listName   The name of the List
	 *
	 * @return string               The ID of the list name provided.
	 */
    public function listIdentification($listName){

    	$listInfo = $this->getListsIdentification();


	    if(empty($listInfo)){
		    $listInfo = $this->getListsIdentification();
	    }

	    return $listInfo[$listName]['id'];
    }

	/**
	 * Returns the name of the lists and their corresponding Identification as an Array.
	 *
	 * $lists is a multi-dimensions array.
	 *
	 * The first foreach gets inside the outer array to be able to access it as a regular array.
	 * The second foreach turns it into an associative array so we can distinguish the keys and the values.
	 *
	 * Returns a multi-dimensional array with the name of the list set up as the main Array Identifier.
	 * Example: $listIdentificationArray[$listName]['id'];
	 *
	 * @return multi-dimensional array
	 */
	private function getListsIdentification()
	{
	    $lists = $this->get('lists');

	    $listIdentificationArray = array();

		if (is_array($lists) || is_object($lists)){

		    foreach($lists as $innerArray){

			    if (is_array($innerArray) || is_object($innerArray)){

				    foreach($innerArray as $key => $value){

					    $id = $innerArray[$key]['id'];
					    $name = $innerArray[$key]['name'];

					    $listIdentificationArray[$name]['id'] = $id;

				    }
			    }

			    return $listIdentificationArray;
		    }

			return $listIdentificationArray;
		}

	}

	public function getListID()
	{
		return $this->getListsIdentification();
	}

    /**
     *  Returns the Merge Field Url
     *
     * @return  string  Return Merge URI
     */
    private function returnMergeFieldUrl($listID)
    {
    	$mergeFieldUrl = '/lists/'.$listID.'/merge-fields/';

    	return $mergeFieldUrl;
    }

	/**
	 * Creates the Initial Merge Fields for the list provided. The Merge Fields are hard coded for a base standard.
	 * If you would like to do any changes please add the new merge field in $mergeFieldData and add the field name
	 * to the $mergeFieldNames array.
	 *
	 * @param $listID
	 */
    public function initiateMergeFields($listID)
    {
		$mergeFieldNames = ['User ID', 'Referred By', 'Referred', 'Awarded', 'Profile Link', 'Referral Link'];

	    $mergeFieldUrl = $this->returnMergeFieldUrl($listID);

	    foreach($mergeFieldNames as $key => $value){

			$data = $this->mergeFieldData($value);

			$this->post($mergeFieldUrl, $data);

		}

    }

	/**
	 * Creates Merge Fields.
	 *
	 * The $data that is received from $mergeFieldData depends on the $mergeFieldName that is passed along.
	 *
	 * @param $listID   ID of the list that will receive the new merge field.
	 * @param $mergeFieldName   Field Names available: User ID, Referred By, Total, Awarded
	 */
    public function createMergeField($listID, $mergeFieldName)
    {
    	$data = $this->mergeFieldData($mergeFieldName);

    	$mergeFieldUrl = $this->returnMergeFieldUrl($listID);

    	$this->post($mergeFieldUrl, $data);
    }

	/**
	 * Returns $data array to create a merge field. Add new switch case to add a new column possibility.
	 *
	 * I did not keep this D.R.Y. for simplicity.
	 *
	 * @param $mergeFieldName   Field Names available: User ID, Referred By, Total, Awarded
	 *
	 * @return array
	 */
    private function mergeFieldData($mergeFieldName)
    {
    	switch($mergeFieldName){
		    case 'User ID':

			    $data = [
				    'name' => 'User ID',
				    'type' => 'number',
				    'tag' => 'USERID',
				    'default_value' => '0'
			    ];

			    return $data;
		    	break;
		    case 'Referred By':

			    $data = [
				    'name' => 'Referred By',
				    'type' => 'number',
				    'tag' => 'REFBY',
				    'default_value' => '0'
			    ];

			    return $data;
			    break;
			case 'Referred':

			    $data = [
				    'name' => 'Referred',
				    'type' => 'number',
				    'tag' => 'REFD',
				    'default_value' => '0'
			    ];

			    return $data;
		    	break;
		    case 'Awarded':

			    $data = [
				    'name' => 'Awarded',
				    'type' => 'number',
				    'tag' => 'AWARDED',
				    'default_value' => '0'
			    ];

			    return $data;

		    	break;
		    case 'Profile Link':
		    	$data = [
		    	    'name' => 'Profile Link',
				    'type' => 'text',
				    'tag' => 'PLINK',
				    'default' => '0'
			    ];

		    	return $data;
		    	break;
		    case 'Referral Link':
		    	$data = [
		    		'name' => 'Referral Link',
				    'type' => 'text',
				    'tag' => 'RLINK',
				    'default' => '0'
			    ];

		    	return $data;
		    	break;
	    }
    }

	/**
	 * Returns the Members URI with the list provided.
	 *
	 * @param $listID   List Id of the List
	 *
	 * @return string
	 */
    public function membersUrl($listID)
    {
    	$membersUrl = '/lists/'.$listID.'/members/';

    	return $membersUrl;
    }

	/**
	 * Returns a string with the list id and the members hash id.
	 *
	 * @param   $membersUrl     string
	 * @param   $memberID       string
	 *
	 * @return  string
	 */
	public function membersUrlWithHash($listID, $memberID)
	{
		return $membersUrlWithSubID = $this->membersUrl($listID) . $memberID;
	}

	/**
	 * Loops through the list and updates the subscribers USERID depending on their position in the array.
	 *
	 * @param $listID
	 */
	public function initiateUniqueIdentifiers($pageLinks, $listID)
	{

		$members = $this->retrieve_audience_information_from_file();

        $batch = $this->new_batch();

		foreach($members as $member => $member_hash){

            if ( ! isset( $i ) ) {
                $i = 1;
            } else {
                $i ++;
            }

            if($member == 'Email Address'){ continue; }

		    $membersUrlWithSubID = $this->membersUrlWithHash($listID, $member_hash);
		    $memberLinks = $this->createMemberLinks($pageLinks, $i,$member_hash, $listID);
		    $this->addMergeFiledDataToMemberArray('USERID', $i );
		    $this->addMergeFiledDataToMemberArray('REFBY', '0' );
		    $this->addMergeFiledDataToMemberArray('REFD', '0' );
		    $this->addMergeFiledDataToMemberArray('AWARDED', '0' );
		    $this->addMergeFiledDataToMemberArray( 'PLINK', $memberLinks['Profile Link']);
		    $this->addMergeFiledDataToMemberArray('RLINK', $memberLinks['Referral Link']);
		    $formatted_data = $this->format_merge_field_data();

		    $batch->patch((string)$i, $membersUrlWithSubID, $formatted_data);

		}
        $batch->execute();

    }

	public function retrieve_audience_information_from_file()
    {
        $filename = __DIR__ . '/mailchimp_list.csv';

        $members_array = array();
        $handle = fopen($filename, 'r');
        while(($content = fgetcsv($handle)) !== FALSE){
            if($content[0]){
                $subscriber_hash = $this->subscriberHash($content[0]);
                $members_array[$content[0]] = $subscriber_hash;
            }

        }
        fclose($handle);

        return (array)$members_array;
    }

    public function updateMemberMergeField($membersUrlWithSubID, $mergeFieldName, $mergeFieldValue)
    {
        $data = array(
            'merge_fields' =>
                array($mergeFieldName => $mergeFieldValue)
        );
        $this->patch($membersUrlWithSubID, $data);
    }

	/**
	 * Retrieves a subscriber's merge field value. Recommended for integers.
	 *
	 * @param $list     string: List of where the subscriber is at.
	 * @param $sub      string: Subscriber's ID
	 * @param $type     string: Case-Sensitive - Merge Field Name
	 *
	 * @return int      If empty, it will return a 0
	 *
	 * @throws Exception
	 */
	public function user_merge_field($list, $sub, $type)
	{
		$mailchimp_api = $this->get('lists/'.$list.'/members/'.$sub);

		$value = $mailchimp_api['merge_fields'][$type];

		if(empty($value)){
			$value = 0;
			return $value;
		}

		return $value;

	}

	public function format_merge_field_data()
	{
        $formatted_data = array(
            'merge_fields' =>
                $this->merge_field_data
        );

        return $formatted_data;
	}

	public function addMergeFiledDataToMemberArray($mergeFieldName, $mergeFieldValue)
    {
        $this->merge_field_data[$mergeFieldName] = $mergeFieldValue;
    }

	public function retrieveMemberData($memberUrlWithSubID, $firstLevelValue = null, $secondLevelValue = null, $thirdLevelValue = null)
	{
		$member = $this->get($memberUrlWithSubID);

		if(!is_null($thirdLevelValue)){

			return $member["$firstLevelValue"]["$secondLevelValue"]["$thirdLevelValue"];

		}elseif(!is_null($secondLevelValue)){

			return $member["$firstLevelValue"]["$secondLevelValue"];

		}elseif(!is_null($firstLevelValue)){

			return $member["$firstLevelValue"];

		}else{

			return $member;

		}
	}

	public function createMemberLinks($pageLinks, $memberID, $memberSub, $memberList)
	{

        $memberProfileLink = $pageLinks[0].'?id='.$memberID.'&list='.$memberList.'&sub='.$memberSub;
        $memberReferralLink = $pageLinks[1].'?list='.$memberList.'&sub='.$memberSub;

		$memberLinks = array('Profile Link' => $memberProfileLink, 'Referral Link' => $memberReferralLink);

		return $memberLinks;

	}


}
