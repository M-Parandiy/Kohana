<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Social_Mail extends Controller_Main {
    
    const USER_LOGO_PATH = 'media/user_logos';
    
    public function before() {

        $this->accessRules = array(
            'any' => array(), //url, которые доступны всем.
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
        $config = Kohana::$config->load('socials.mail'); 
        
        
        if ($this->request->post()) {
            $post = $this->request->post();
            
            if(!isset($_SESSION['mailru_data'])) die;
            
            if($who == 'client')
            {
                $groupname = 'client';
            }
            elseif($who == 'agent')
            {
                $groupname = 'agent';
            }
            
            $key = base64_encode($_SESSION['mailru_data']->email);
            $group = ORM::factory('Group')->where('name', '=', $groupname)->find();
            $user = ORM::factory('User');
            
            $user->email = $_SESSION['mailru_data']->email;
            $user->password = trim($post['password']);
            $user->key = $key;
            $user->mail_id = $_SESSION['mailru_data']->uid;
            $user->group_id = $group->id;
            $user->save();
            
            /** Загрузка фото **/
            $directory = DOCROOT . self::USER_LOGO_PATH;
            $filename = Text::random('alnum', 20) . '.jpg';
            if (!is_dir($directory)) {
                mkdir($directory, 0777);
            }
            $url  = $_SESSION['mailru_data']->pic_180;
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
                        
                        $client->firstname = $_SESSION['mailru_data']->first_name;
                        $client->lastname = $_SESSION['mailru_data']->last_name;
                        
                        $client->user_id = $user->id;
                        $client->save();
                        $user->add('roles', ORM::factory('Role')->where('name', '=', 'login')->find());
                    } catch (ORM_Validation_Exception $e) {
                        $errors = $e->errors('valid');
                        echo $errors;
                    }
                break;case 'agent' :
                    try {
                        $agent = ORM::factory('Agent');
                        $agent->logo = $filename;
                        $agent->title = $_SESSION['mailru_data']->first_name.' '.$_SESSION['mailru_data']->last_name;
                        $agent->user_id = $user->id;
                        $agent->save();
                        $user->add('roles', ORM::factory('Role')->where('name', '=', 'login')->find());
                    } catch (ORM_Validation_Exception $e) {
                        $errors = $e->errors('valid');
                        echo $errors;
                    }
                break;
            }
            Auth::instance()->login($_SESSION['mailru_data']->email, trim($post['password']),true);
            unset($_SESSION['mailru_data']);
            echo View::factory('social/mail/register')->set('reload', 1);
        }
        else
        {
            if(isset($_GET['code']))
            {
                $url = 'https://connect.mail.ru/oauth/token';
                $fields = 'client_id='.$config['id'].'&client_secret='.$config['secret_key'].'&grant_type=authorization_code&code='.$_GET['code'].'&redirect_uri='.$config['redirect_url'].$who;
      
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
                curl_setopt($curl, CURLOPT_URL, $url);
                $response = curl_exec($curl);
                curl_close($curl);
                
                $tokeninfo = json_decode($response);
                
                $sign = md5('app_id='.$config['id'].'method=users.getInfosecure=1session_key='.$tokeninfo->access_token.$config['secret_key']);
                
                $url = 'http://www.appsmail.ru/platform/api?method=users.getInfo&secure=1&app_id='.$config['id'].'&session_key='.$tokeninfo->access_token.'&sig='.$sign;
                
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_URL, $url);
                $response = curl_exec($curl);
                curl_close($curl);
                
                $userinfo = json_decode($response);
                
                /** Если пользователь существует выполняем авторизацию. **/
                $user = ORM::factory('User');
                if($user->where('mail_id', '=', $userinfo[0]->uid)->find()->loaded())
                {
                    Auth::instance()->force_login($user->where('mail_id', '=', $userinfo[0]->uid));
                    echo View::factory('social/mail/register')->set('reload', 1);
                    die;
                }
                
                $_SESSION['mailru_data'] = $userinfo[0];
                echo View::factory('social/mail/register')->set('reload', 0);
                die;
            }
            else
            {
                $url = 'https://connect.mail.ru/oauth/authorize?client_id='.$config['id'].'&response_type=code&redirect_uri='.$config['redirect_url'].$who;
                header("Location: $url");
                die;
            }
        }
    }
    
    public function sign_server_server(array $request_params, $secret_key) {
        ksort($request_params);
        $params = '';
        foreach ($request_params as $key => $value) {
            $params .= "$key=$value";
        }
        return md5($params . $secret_key);
    }
}