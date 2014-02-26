<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Social_VK extends Controller_Main {
    
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
        $config = Kohana::$config->load('socials.vk'); 
        
        /** Если нету $_GET['code'] переадресовываем на страницу авторизации вк **/
        if(!isset($_GET['code']))
        {
            header("Location: https://oauth.vk.com/authorize?client_id=".$config['app_id']."&scope=".$config['permissions']."&redirect_uri=".$config['redirect_uri'].$who."&response_type=code&v=".$config['api_version']);
            exit();
        }
        $reload = 0;
        
        if ($this->request->post()) {
            $post = $this->request->post();
            
            if(!isset($_SESSION['vk_data'])) die;
            
            $key = base64_encode(trim($post['email']));
            
            if($who == 'client')
            {
                $groupname = 'client';
            }
            elseif($who == 'agent')
            {
                $groupname = 'agent';
            }
            
            $group = ORM::factory('Group')->where('name', '=', $groupname)->find();
            $user = ORM::factory('User');
            
            $user->email = trim($post['email']);
            $user->password = trim($post['password']);
            $user->key = $key;
            $user->vk_id = $_SESSION['vk_data']['uid'];
            $user->group_id = $group->id;
            $user->save();
            
            /** Страна **/
            $check_country = ORM::factory('Country')->where('name', '=', $_SESSION['vk_data']['country']['name'])->find();
            if($check_country->loaded())
            {
                $insert_country = $check_country->id;
            }
            else
            {
                $new_country = ORM::factory('Country')->set('name', $_SESSION['vk_data']['country']['name'])->save();
                $insert_country = $new_country->id;
            }
            
            /** Город **/
            $check_city = ORM::factory('City')->where('name', '=', $_SESSION['vk_data']['city']['name'])->find();
            if($check_city->loaded())
            {
                $insert_city = $check_city->id;
            }
            else
            {
                $new_city = ORM::factory('City')->set('name', $_SESSION['vk_data']['city']['name'])->set('id_country', $insert_country)->save();
                $insert_city = $new_city->id;
            }
            
            /** Загрузка фото **/
            $directory = DOCROOT . self::USER_LOGO_PATH;
            $extension = strtolower(pathinfo($_SESSION['vk_data']['photo_medium'], PATHINFO_EXTENSION));
            $filename = Text::random('alnum', 20) . '.' . $extension;
            if (!is_dir($directory)) {
                mkdir($directory, 0777);
            }
            $url  = $_SESSION['vk_data']['photo_medium'];
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
                        $client->city_id = $insert_city;
                        $client->country_id = $insert_country;
                        $client->logo = $filename;
                        $client->firstname = $_SESSION['vk_data']['first_name'];
                        $client->lastname = $_SESSION['vk_data']['last_name'];
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
                        $agent->city_id = $insert_city;
                        $agent->country_id = $insert_country;
                        $agent->logo = $filename;
                        $agent->title = $_SESSION['vk_data']['first_name'].' '.$_SESSION['vk_data']['last_name'];
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
            unset($_SESSION['vk_data']);
            $reload = 1;
        }
        else
        {
            $code = $_GET['code'];
            
            $APP_ID = $config['app_id'];
            $APP_SECRET = $config['app_secret'];
            $REDIRECT_URI = $config['redirect_uri'];
            
            /** Выполняем запрос к серверу авторизации **/
            $request_url = 'https://oauth.vk.com/access_token?client_id='.$APP_ID.'&client_secret='.$APP_SECRET.'&code='.$code.'&redirect_uri='.$REDIRECT_URI.$who;
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_URL, $request_url);
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response);
            $token = $result->access_token;
            $uid = $result->user_id;
            
            $response = $this->sendRequest('getProfiles', array('uids' => $uid, 'fields' => 'uid,first_name,last_name,city,country,contacts,photo_medium'), $token);
            
            /** Если пользователь существует выполняем авторизацию. **/
            $user = ORM::factory('User');
            if($user->where('vk_id', '=', $response['response'][0]['uid'])->find()->loaded())
            {
                Auth::instance()->force_login($user->where('vk_id', '=', $response['response'][0]['uid']));
                echo View::factory('social/vk/register')->set('reload', 1);
                die;
            }
            
            /** Получаем данные города и страны **/
            $city = $this->sendRequest('database.getCitiesById', array('city_ids' => $response['response'][0]['city']), $token);
            $country = $this->sendRequest('database.getCountriesById', array('country_ids' => $response['response'][0]['country']), $token);
            
            /** Заносим данные в сессию **/
            $_SESSION['vk_data'] = $response['response'][0];
            $_SESSION['vk_data']['city'] = $city['response'][0];
            $_SESSION['vk_data']['country'] = $country['response'][0];
        }
        echo View::factory('social/vk/register')->set('reload', $reload);
    }
    
    public function action_test()
    {
        $res = $this->sendRequest('video.get', array('videos' => '17626135_166893988'), 'zxqWLIuKEkbaecX5BgAf');
        print_r($res);
    }
    
    public function sendRequest($method, $parameters = array(), $token = false)
    {
        $pline = '';
        if(count($parameters) > 0)
        {
            foreach($parameters as $k => $v)
            {
                $pline .= $k.'='.$v.'&';
            }
        }
        
        $pline = rtrim($pline, '&');
        
        $url = 'https://api.vk.com/method/'.$method.'?'.$pline;
        if($token != false)
        {
            $url .= '&access_token='.$token;
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);
        curl_close($curl);
        
        
        return json_decode(mb_convert_encoding($response, "UTF-8"), true);
    }
    
}