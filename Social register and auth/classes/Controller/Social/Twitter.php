<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Social_Twitter extends Controller_Main {
    
    const USER_LOGO_PATH = 'media/user_logos';
    
    public function before() {

        $this->accessRules = array(
            'any' => array('test'), //url, которые доступны всем.
            'guest' => array(
                'register'
            ),                   //url, которые доступны для гостей
            'client' => array(), //url, которые доступны для клиента
            'agent' => array(),  //url, которые доступны для агента
            'admin' => array()   //url, которые доступны для админа
        );

        parent::before();
    }
    
    public function action_register()
    {
        $who = $this->request->param('id');
        
        /** Получаем данные приложения из конфигурации **/
        $config = Kohana::$config->load('socials.tw'); 
        
        if ($this->request->post()) {
            $post = $this->request->post();
            
            if(!isset($_SESSION['twitter_data'])) die;
            
            if($who == 'client')
            {
                $groupname = 'client';
            }
            elseif($who == 'agent')
            {
                $groupname = 'agent';
            }
            
            $key = base64_encode($post['email']);
            $group = ORM::factory('Group')->where('name', '=', $groupname)->find();
            $user = ORM::factory('User');
            
            $user->email = trim($post['email']);
            $user->password = trim($post['password']);
            $user->key = $key;
            $user->tw_id = $_SESSION['twitter_data']->id;
            $user->group_id = $group->id;
            $user->save();
            
            /** Загрузка фото **/
            $directory = DOCROOT . self::USER_LOGO_PATH;
            $extension = strtolower(pathinfo($_SESSION['twitter_data']->profile_image_url, PATHINFO_EXTENSION));
            $filename = Text::random('alnum', 20) . '.' . $extension;
            if (!is_dir($directory)) {
                mkdir($directory, 0777);
            }
            $url  = $_SESSION['twitter_data']->profile_image_url;
            $path = $directory.'/'.$filename;
          
            $fp = fopen($path, 'w');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            $data = curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            
            switch($who)
            {
                case 'client' :
                    try {
                        $client = ORM::factory('Client');
                        $client->logo = $filename;
                        
                        $names = explode(' ', $_SESSION['twitter_data']->name);
                        $client->firstname = $names[0];
                        if(isset($names[1])) $client->lastname = $names[1];
                        
                        $client->user_id = $user->id;
                        $client->save();
                        $user->add('roles', ORM::factory('Role')->where('name', '=', 'login')->find());
                    } catch (ORM_Validation_Exception $e) {
                        $errors = $e->errors('valid');
                        echo $errors;
                    }
                break;
                case 'agent' :
                    try {
                        $agent = ORM::factory('Agent');
                        $agent->logo = $filename;
                        $agent->title = $_SESSION['twitter_data']->name;
                        $agent->user_id = $user->id;
                        $agent->save();
                        $user->add('roles', ORM::factory('Role')->where('name', '=', 'login')->find());
                    } catch (ORM_Validation_Exception $e) {
                        $errors = $e->errors('valid');
                        echo $errors;
                    }
                break;
            }
            Auth::instance()->login(trim($post['email']), trim($post['password']),true);
            unset($_SESSION['twitter_data']);
            echo View::factory('social/twitter/register')->set('reload', 1);
        }
        else
        {
            if(isset($_GET['oauth_token']) && isset($_GET['oauth_verifier']))
            {
                $oauth_nonce = md5(uniqid(rand(), true));
                $oauth_timestamp = time();
                $oauth_token = $_GET['oauth_token'];
                $oauth_verifier = $_GET['oauth_verifier'];
                $oauth_token_secret = $_SESSION['twitter_oauth_token_secret'];
                
                $oauth_base_text = "GET&";
                $oauth_base_text .= urlencode($config['url_access_token'])."&";
                $oauth_base_text .= urlencode("oauth_consumer_key=".$config['consumer_key']."&");
                $oauth_base_text .= urlencode("oauth_nonce=".$oauth_nonce."&");
                $oauth_base_text .= urlencode("oauth_signature_method=HMAC-SHA1&");
                $oauth_base_text .= urlencode("oauth_token=".$oauth_token."&");
                $oauth_base_text .= urlencode("oauth_timestamp=".$oauth_timestamp."&");
                $oauth_base_text .= urlencode("oauth_verifier=".$oauth_verifier."&");
                $oauth_base_text .= urlencode("oauth_version=1.0");
                
                $key = $config['consumer_secret']."&".$oauth_token_secret;
                $oauth_signature = base64_encode(hash_hmac("sha1", $oauth_base_text, $key, true));
                
                $url = $config['url_access_token'];
                $url .= '?oauth_nonce='.$oauth_nonce;
                $url .= '&oauth_signature_method=HMAC-SHA1';
                $url .= '&oauth_timestamp='.$oauth_timestamp;
                $url .= '&oauth_consumer_key='.$config['consumer_key'];
                $url .= '&oauth_token='.urlencode($oauth_token);
                $url .= '&oauth_verifier='.urlencode($oauth_verifier);
                $url .= '&oauth_signature='.urlencode($oauth_signature);
                $url .= '&oauth_version=1.0';
                
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_URL, $url);
                $response = curl_exec($curl);
                curl_close($curl);
                
                parse_str($response, $result);
                
                $oauth_nonce = md5(uniqid(rand(), true));
    
                $oauth_timestamp = time();
                
                $oauth_token = $result['oauth_token'];
                $oauth_token_secret = $result['oauth_token_secret'];
                $screen_name = $result['screen_name'];
                
                $oauth_base_text = "GET&";
                $oauth_base_text .= urlencode($config['url_account_data']).'&';
                $oauth_base_text .= urlencode('oauth_consumer_key='.$config['consumer_key'].'&');
                $oauth_base_text .= urlencode('oauth_nonce='.$oauth_nonce.'&');
                $oauth_base_text .= urlencode('oauth_signature_method=HMAC-SHA1&');
                $oauth_base_text .= urlencode('oauth_timestamp='.$oauth_timestamp."&");
                $oauth_base_text .= urlencode('oauth_token='.$oauth_token."&");
                $oauth_base_text .= urlencode('oauth_version=1.0&');
                $oauth_base_text .= urlencode('screen_name=' . $screen_name);
                
                $key = $config['consumer_secret'] . '&' . $oauth_token_secret;
                $signature = base64_encode(hash_hmac("sha1", $oauth_base_text, $key, true));
                
                // Формируем GET-запрос
                $url = $config['url_account_data'];
                $url .= '?oauth_consumer_key=' . $config['consumer_key'];
                $url .= '&oauth_nonce=' . $oauth_nonce;
                $url .= '&oauth_signature=' . urlencode($signature);
                $url .= '&oauth_signature_method=HMAC-SHA1';
                $url .= '&oauth_timestamp=' . $oauth_timestamp;
                $url .= '&oauth_token=' . urlencode($oauth_token);
                $url .= '&oauth_version=1.0';
                $url .= '&screen_name=' . $screen_name;
                
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_URL, $url);
                $response = curl_exec($curl);
                curl_close($curl);
                
                $user_data = json_decode($response);
                
                /** Если пользователь существует выполняем авторизацию. **/
                $user = ORM::factory('User');
                if($user->where('tw_id', '=', $user_data->id)->find()->loaded())
                {
                    Auth::instance()->force_login($user->where('tw_id', '=', $user_data->id));
                    echo View::factory('social/twitter/register')->set('reload', 1);
                    die;
                }
                
                $_SESSION['twitter_data'] = $user_data;
                echo View::factory('social/twitter/register')->set('reload', 0);
            }
            else
            {
                // рандомная строка (для безопасности)
                $oauth_nonce = md5(uniqid(rand(), true)); // ae058c443ef60f0fea73f10a89104eb9
                
                // время когда будет выполняться запрос (в секундых)
                $oauth_timestamp = time(); // 1310727371
                
                $oauth_base_text = "GET&";
                $oauth_base_text .= urlencode($config['url_request_token'])."&";
                $oauth_base_text .= urlencode("oauth_callback=".urlencode($config['url_callback'].$who)."&");
                $oauth_base_text .= urlencode("oauth_consumer_key=".$config['consumer_key']."&");
                $oauth_base_text .= urlencode("oauth_nonce=".$oauth_nonce."&");
                $oauth_base_text .= urlencode("oauth_signature_method=HMAC-SHA1&");
                $oauth_base_text .= urlencode("oauth_timestamp=".$oauth_timestamp."&");
                $oauth_base_text .= urlencode("oauth_version=1.0");
                
                $key = $config['consumer_secret']."&";
                
                $oauth_signature = base64_encode(hash_hmac("sha1", $oauth_base_text, $key, true));
                
                
                $url = $config['url_request_token'];
                $url .= '?oauth_callback='.urlencode($config['url_callback'].$who);
                $url .= '&oauth_consumer_key='.$config['consumer_key'];
                $url .= '&oauth_nonce='.$oauth_nonce;
                $url .= '&oauth_signature='.urlencode($oauth_signature);
                $url .= '&oauth_signature_method=HMAC-SHA1';
                $url .= '&oauth_timestamp='.$oauth_timestamp;
                $url .= '&oauth_version=1.0';
                
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_URL, $url);
                $response = curl_exec($curl);
                curl_close($curl);
                
                parse_str($response, $result);
                
                $_SESSION['twitter_oauth_token'] = $oauth_token = $result['oauth_token'];
                $_SESSION['twitter_oauth_token_secret'] = $oauth_token_secret = $result['oauth_token_secret'];
                
                $url = $config['url_authorize'];
                $url .= '?oauth_token='.$oauth_token;
                
                header('Location: '.$url);
                die;
            }
            
        }
    }
}