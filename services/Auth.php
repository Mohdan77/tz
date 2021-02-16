<?php

namespace Services;

class Auth
{

    const SUBDOMAIN = 'ashah1221';

    private $link = 'https://' . self::SUBDOMAIN . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

    private $data;

    private $fileName = 'access.txt';


    public function __construct()
    {
        $this->data = [
            'client_id' => '72a171e7-0a50-49ca-b5ed-daec5c0c4b35',
            'client_secret' => '0tEq7yLCoKxZranUktgiLP2tJN90tjpVxHhIGE4c3hjgihQABlxwdtHgNyu94GZl',
            'grant_type' => 'authorization_code',
            'code' => 'def502003347f12c6ea7cabf27c9246010c8dbb5a7422f3271f8d19b1e761925db64ca1425d343f61a4f19c75f919d62f901153302ab31fbb937c275261cf4b894f17a4543cf813db72d2300f43ddcc9f7de1f01b1593b90cb9c2a15242f68448874795a7d8e894c2d2a5f340fc763be5d2c22bd893b8be92e2cf8b0626822f51e6391f37659e9ea160b8daa9d953d4da1c5da6fc6edb3d68e792f02b086ff69b504d25e60d176d60faf07f289051d9c18cc85308773eabfe968ee1733982a1097e4d035649bb5c59a7f47a160bbacaa19e519a93146d0bb87ba741358f7f225528c2e647a26bb9b67361d699fe1bef74d33c667a5ebc580e9fbb25b0866cacbd9e1aaa0e3aaed9dc18dba91bd7862cabb9e4e9f736f6abf103620bc64ce1441ecbbe0d4e88af193b973c974f2b1801b7728c12ce48ce28ccdd170285233e600a779629a91470a4af5078657acbf3958de256fb158aebf13714298e898ca033d967d4216eabcb0ccded0799e4b7a2c9ad5419a5b73b05318a3e73ea05032316e3371fac7842b89de1e6ada4b5959fefe01c1984f146d47f16c1f194ce74f95c097cc4f75659712fa9469044c3047a1bab29baa3ec79e9c769b39e48830ab863b8aef3e4bf4b3',
            'redirect_uri' => 'http://mebel-dom-grozny.ru/',
        ];


        if (!$this->getToken()) $this->auth();
    }


    public function auth()
    {
        /**
         * Нам необходимо инициировать запрос к серверу.
         * Воспользуемся библиотекой cURL (поставляется в составе PHP).
         * Вы также можете использовать и кроссплатформенную программу cURL, если вы не программируете на PHP.
         */
        $curl = curl_init(); //Сохраняем дескриптор сеанса cURL
        /** Устанавливаем необходимые опции для сеанса cURL  */
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $this->link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($this->data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        /** Теперь мы можем обработать ответ, полученный от сервера. Это пример. Вы можете обработать данные своим способом. */
        $code = (int)$code;
        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try
        {
            /** Если код ответа не успешный - возвращаем сообщение об ошибке  */
            if ($code < 200 || $code > 204) {
                throw new \Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        }
        catch(\Exception $e)
        {
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }

        /**
         * Данные получаем в формате JSON, поэтому, для получения читаемых данных,
         * нам придётся перевести ответ в формат, понятный PHP
         */
        $response = json_decode($out, true);

        $this->saveDataAccess($response);

        return $response;
    }


    //сохраняю в файлик массив с ответом
    public function saveDataAccess($data)
    {
        $data['save_time'] = time();

        $access = serialize($data);

        file_put_contents($this->fileName, $access);
    }

    //получаю ранее сохраненый массив
    public function getDataAccess()
    {
        $data = file_get_contents($this->fileName);

        $result = unserialize($data);

       return $result;
    }

    //получаю токен, сорян за костыль с проверкой начинал прям перед сном типа сохраняю время получения токена
    //затем складываю его со временем жизни токена и тип если больше текущего времени делаю запрос на refresh
    public function getToken()
    {
        $data = $this->getDataAccess();

        if (!$data) return false;

        $endTime = $data['save_time'] + $data['expires_in'];

        if ($endTime > time()) return $data;

        return $this->refresh();
    }


    public function refresh()
    {

        $dataAccess = $this->getDataAccess();
        $this->data['grant_type'] = 'refresh_token';
        $this->data['refresh_token'] = $dataAccess['refresh_token'];
        unset($this->data['save_time']);

        $curl = curl_init(); //Сохраняем дескриптор сеанса cURL
        /** Устанавливаем необходимые опции для сеанса cURL  */
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $this->link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($this->data));
//        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        /** Теперь мы можем обработать ответ, полученный от сервера. Это пример. Вы можете обработать данные своим способом. */
        $code = (int)$code;
        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try
        {
            /** Если код ответа не успешный - возвращаем сообщение об ошибке  */
            if ($code < 200 || $code > 204) {
                throw new \Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        }
        catch(\Exception $e)
        {
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }

        /**
         * Данные получаем в формате JSON, поэтому, для получения читаемых данных,
         * нам придётся перевести ответ в формат, понятный PHP
         */
        $response = json_decode($out, true);


        $this->saveDataAccess($response);

        return $this->getDataAccess();
    }


}