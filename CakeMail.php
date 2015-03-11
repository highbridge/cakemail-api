<?php 

/**
 * Cake_api
 *  - Custom API for working with CakeMail
 * 
 * @author Michael Roth, Highbridge Creative, Inc.
 * @version 0.0.1
 */

class Cake_api {
  
  // Box Strategies
  private $user_email  = '';
  private $user_pass   = '';
  private $user_key    = '';
  private $api_key     = '';
  private $client_id   = 000000; // client id if managing multiple
  
  private $api_url     = 'https://api.wbsrvc.com';
  private $logged_in   = FALSE;
  
  public $data;
  public $list_id;
  public $record_id;
  public $email;
  public $trigger;
  public $error = array();

  function __construct($fields = array()){
    
    // set fields   
    $this->list_id   = empty($fields['list_id']) ? NULL : $fields['list_id'];
    $this->record_id = empty($fields['record_id']) ? NULL : $fields['record_id'];
    $this->email     = empty($fields['email']) ? '' : $fields['email'];
    $this->data      = empty($fields['data']) ? NULL : $fields['data'];
    $this->trigger   = empty($fields['trigger']) ? FALSE : $fields['trigger'];

    // Attempt Login
    $login = $this->call('/User/login', array(
                    'email' => $this->user_email, 
                    'password' => $this->user_pass,
                    'client_id' => $this->client_id
                    )
                ); 
            
    if(is_string($login)){
        $this->error['login'] = $login;
        $this->logged_in = FALSE;
    } else {
        $this->logged_in = TRUE;
    }
  }

/**
 * call
 *  - does the curl request to the CAKE API
 * 
 * @param $url - the url to call (method)
 * @param $params - parameters to pass
 */
 private function call($url, $params) {
    try {

      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, $this->api_url . $url); 
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('apikey:'.$this->api_key));
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

      $result = curl_exec($ch);

      if ($result === false) {

        throw new Exception('Curl error: ' . curl_error($ch));

      } else {

        if (!$result = json_decode($result)) {
          throw new Exception('API Key Validation Error for ' . $this->api_key . '. Contact your administrator!');
        } 

        if ($result->status != 'success') {
          throw new Exception($result->data);
        }

      }
      
      curl_close($ch);
      return $result->data;

    } catch (Exception $e) {
      curl_close($ch);
      return $e->getMessage();
    }
  }


 /**
  * update_record
  *  - update an existing member entry for a specific list
  * 
  * @param $list_id
  * @param $record_id
  * @param $data
  */
  public function update_record($list_id=NULL, $record_id=NULL, $data){
    // Attempt to update a record   
    try{
        if($this->logged_in){
            $result = $this->call('/List/UpdateRecord', array(
                        'user_key'  => $this->user_key, 
                        'client_id' => $this->client_id,
                        'list_id'   => ($list_id == NULL) ? $this->list_id : $list_id, 
                        'record_id' => ($record_id == NULL) ? $this->record_id : $record_id,  
                        'data'      => $data
                        )
                    );  
        } else {
            throw new Exception("Not logged in, unable to update record");
        }
        
        if($result != 1){
            throw new Exception("Error, unable to update record {$record_id}");
        }
        
    } catch(Exception $e){
        $this->error['update_record'] = $e->getMessage();
    }
    
    return $result;
  }
  
  /**
   * get_record
   *  - get a member record from a specific list
   * 
   * @param $list_id
   * @param $record_id
   */
  public function get_record($list_id, $record_id){
    // Attempt to get a record
    try{
        if($this->logged_in){
            $record = $this->call('/List/GetRecord', array(
                        'user_key'  => $this->user_key, 
                        'list_id'   => $list_id, 
                        'client_id' => $this->client_id,
                        'record_id' => $record_id
                        )
                    );
        }  else {
            throw new Exception("Not logged in, unable to get record");
        }
        
        if(!isset($record->id)){
            throw new Exception("Error, unable to get record {$record_id}");
        }
        
    } catch(Exception $e){
        $this->error['get_record'] = $e->getMessage();
    }
    
    return $record;
  }
  
  /**
   * create_record
   *  - create a member record for a specific list
   * 
   * @param $list_id
   * @param $email
   * @param $data
   * @param $trigger
   */
  public function create_record($list_id, $email, $data){
        
    // attempt to create a record
    try{
        if($this->logged_in){
            $record = $this->call('/List/SubscribeEmail', array(
                         'user_key' => $this->user_key, 
                         'list_id' => $list_id, 
                         'client_id' => $this->client_id,
                         'email' => $email, 
                         'triggers' => $this->trigger, 
                         'data' => $data
                         )
                    );
        } else {
            throw new Exception("Not logged in, unable to create record");
        }

        if(intval($record) == 0 && $record != '0'){
            throw new Exception($record);
        }
    
    } catch(Exception $e){
        $this->error['create_record'] = $e->getMessage();
    }
    return $record;
  }
  
  /**
   * update_record_by_email
   *  - get all records from a list
   * 
   * @return $list - array of records
   */
  public function get_list_records($list_id=NULL,$client_id=NULL){
    // Attempt to get a record
    try{
        if($this->logged_in){
            
            ## check the total count of the list
            $list_count = $this->call('/List/Show', array(
                        'user_key'  => $this->user_key, 
                        'list_id'   => ($list_id != NULL) ? $list_id : $this->list_id,
                        'client_id' => ($client_id != NULL) ? $client_id : $this->client_id,
                        'count'     => 'true'
                        )
                    );
            
            if(is_string($list_count)){
                throw new Exception("Error: Unable to get list records - " . $list_count);
            } else {
                
                $count = $list_count->count;
                
                ## get the members in batches
                $list = array(); 
                for ($i=0; $i < $count; $i += 1000) { 
                    $temp_list = $this->call('/List/Show', array( 
                        'user_key' => $this->user_key, 
                        'list_id' => ($list_id != NULL) ? $list_id : $this->list_id,
                        'limit' => 1000, 
                        'offset' => $i,
                        'client_id' => $this->client_id 
                        ) 
                    );
                    $list = array_merge($list, $temp_list->records); 
                }
                
            }
        }  else {
            throw new Exception("Error: Not logged in, unable to get record");
        }
        
    } catch(Exception $e){
        $this->error['get_list_records'] = $e->getMessage();
    }
    
     $list = !empty($list) ? $list : $this->error;
    
    return $list;
  }
  
  /**
   * relay_send
   * - send an email to one user
   * url:  http://dev.cakemail.com/api/Relay/Send
   * 
   * @param $params - array of necessary parameters to send the relay
   */
  public function send_relay($params){
    
    try{
        if($this->logged_in){
            $result = $this->call('/Relay/Send', array(
                         'user_key'     => $this->user_key, 
                         'email'        => $params['email'], 
                         'encoding'     => 'utf-8',
                         'subject'      => $params['subject'], 
                         'sender_email' => $params['sender_email'], 
                         'sender_name'  => $params['sender_name'],
                         'html_message' => $params['html_message'],
                         'client_id'    => $this->client_id,
                         'data'         => $params['data']
                         )
                    );
        } else {
            throw new Exception("Not logged in, unable to send relay email");
        }

        if($result == 'Error'){ // TODO ????
            throw new Exception($result);
        }
    
    } catch(Exception $e){
        $this->error['send_relay'] = $e->getMessage();
    }
    
    return $result;
  }

}
?>
