<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Social_Facebook extends Controller_Main {
    
    const USER_LOGO_PATH = 'media/user_logos';
    
    public function before() {

        $this->accessRules = array(
            'any' => array(), //url, ������� �������� ����.
            'guest' => array(
                'register'
            ),                   //url, ������� �������� ��� ������
            'client' => array(), //url, ������� �������� ��� �������
            'agent' => array(),  //url, ������� �������� ��� ������
            'admin' => array()   //url, ������� �������� ��� ������
        );

        parent::before();
    }
    
    public function action_register()
    {
        /** �������� ������ ���������� �� ������������ **/
        $config = Kohana::$config->load('socials.fb');
        $who = $this->request->param('id'); 
        $reload = 0;
        
        if ($this->request->post())
        {
            $post = $this->request->post();
            if(!isset($_SESSION['fb_data'])) die;
            
            if($who == 'client')
            {
                $groupname = 'client';
            }
            elseif($who == 'agent')
            {
                $groupname = 'agent';
            }
            
            $key = base64_encode($post['email']);
            $group = ORM::factory('Group')->getGroupByName($groupname);
            $user = ORM::factory('User');
            
            $user->email = trim($post['email']);
            $user->password = trim($post['password']);
            $user->key = $key;
            $user->fb_id = $_SESSION['fb_data']->id;
            $user->group_id = $group->id;
            $user->save();
            
            /** ������ **/
            $check_country = ORM::factory('Country')->getCountryByName($_SESSION['fb_data']->country);
            if($check_country->loaded())
            {
                $insert_country = $check_country->id;
            }
            else
            {
                $new_country = ORM::factory('Country')->setCountry($_SESSION['fb_data']->country);
                $insert_country = $new_country->id;
            }
            
            /** ����� **/
            $check_city = ORM::factory('City')->getCityByName($_SESSION['fb_data']->location->name);
            if($check_city->loaded())
            {
                $insert_city = $check_city->id;
            }
            else
            {
                $new_city = ORM::factory('City')->setCity($_SESSION['fb_data']->location->name, $insert_country);
                $insert_city = $new_city->id;
            }
            
            /** �������� ���� **/
            $directory = DOCROOT . self::USER_LOGO_PATH;
            $extension = strtolower(pathinfo($_SESSION['fb_data']->picture, PATHINFO_EXTENSION));
            $filename = Text::random('alnum', 20) . '.' . $extension;
            if (!is_dir($directory)) {
                mkdir($directory, 0777);
            }
            $url  = str_replace('https:', 'http:', $_SESSION['fb_data']->picture);
            $path = $directory.'/'.$filename;
          
            $fp = fopen($path, 'w');
            $data = file_get_contents($url);
            $file = fopen($path, 'w+');
            fputs($file, $data);
            fclose($file);
            
            switch($who)
            {
                case 'client' :
                    try {
                        $client = ORM::factory('Client');
                        $client->city_id = $insert_city;
                        $client->country_id = $insert_country;
                        $client->logo = $filename;
                        $client->firstname = $_SESSION['fb_data']->first_name;
                        $client->lastname = $_SESSION['fb_data']->last_name;
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
                        $agent->title = $_SESSION['fb_data']->first_name.' '.$_SESSION['fb_data']->last_name;
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
            unset($_SESSION['fb_data']);
            
            $reload = 1;
            echo View::factory('social/facebook/register')->set('reload', $reload);
        }
        else
        {
            if(!isset($_GET['code']))
            {
                $_SESSION['state'] = md5(uniqid(rand(), TRUE)); 
                $link = $config['url_oauth'].'?client_id='.$config['app_id'].'&redirect_uri='.urlencode($config['url_callback'].$who)."&state=".$_SESSION['state'];
                
                header("Location: $link");
                die;
            }
            else
            {
                /** ��������� ������ � ������� ����������� **/
                $request_url = $config['url_access_token'].'?client_id='.$config['app_id'].'&redirect_uri='.urlencode($config['url_callback'].$who).
                '&client_secret='.$config['app_secret'].'&code='.$_GET['code'];
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_URL, $request_url);
                $response = curl_exec($curl);
                curl_close($curl);
                $tokenInfo = null;
                parse_str($response, $tokenInfo);
    
                /** �������� ���� � ������������ **/
                if (count($tokenInfo) > 0 && isset($tokenInfo['access_token'])) {
                    $request_url = $config['url_get_me'].'?access_token='.$tokenInfo['access_token'].'&locale=ru_RU';
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_URL, $request_url);
                    $response = curl_exec($curl);
                    curl_close($curl);
                    $userInfo = json_decode($response);
                    
                    /** ���� ������������ ���������� ��������� �����������. **/
                    $user = ORM::factory('User');
                    if($user->where('fb_id', '=', $userInfo->id)->find()->loaded())
                    {
                        Auth::instance()->force_login($user->where('vk_id', '=', $userInfo->id));
                        echo View::factory('social/facebook/register')->set('reload', 1);
                        die;
                    }
                    
                    /** �������� ������ ������������ � �������� **/
                    $enc = urlencode('SELECT current_location, pic_big FROM user WHERE uid IN ('.$userInfo->id.')');
                    $request_url = 'https://graph.facebook.com/fql?q='.$enc.'&access_token='.$tokenInfo['access_token'].'&locale=ru_RU';
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_URL, $request_url);
                    $response = curl_exec($curl);
                    curl_close($curl);
                    $userCountry = json_decode($response);
                    
                    /** �������� ������� �������� ������ **/
                    $request_url = 'http://maps.google.com/maps/api/geocode/json?address='.$userCountry->data[0]->current_location->country.'&sensor=false&language=ru';
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_URL, $request_url);
                    $response = curl_exec($curl);
                    curl_close($curl);
                    $userCountryRus = json_decode($response);
                    
                    if(isset($userInfo->id))
                    {
                        /** ������� ������ � ������ **/
                        $_SESSION['fb_data'] = $userInfo;
                        $_SESSION['fb_data']->picture = $userCountry->data[0]->pic_big;
                        $_SESSION['fb_data']->country = $userCountryRus->results[0]->address_components[0]->long_name;
                        
                        echo View::factory('social/facebook/register')->set('reload', $reload);
                    }
                }
            }
        }
    }
    
}