<?php

/*
Copyright (c) 2015, Adam Schubert <adam.schubert@sg1-game.net>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. All advertising materials mentioning features or use of this software
   must display the following acknowledgement:
   This product includes software developed by the <organization>.
4. Neither the name of the <organization> nor the
   names of its contributors may be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY <COPYRIGHT HOLDER> ''AS IS'' AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Salamek\Servis24;

/**
 * Request implemented as file_get_contents, so we dont need cURL and openbase_dir = none
 */
class request
{
  private $url;
  private $method;
  private $parameters = array();
  private $cookies =  array();
  private $maxRedirections = 10;
  private $redirectionsCount = 0;


  public function __construct($url, $method, $parameters = array(), $cookies = array())
  {
    $this->url = $url;
    $this->method = $method;
    $this->parameters = $parameters;
    $this->cookies = $cookies;
  }

  public function setMaxRedirections($maxRedirections)
  {
    $this->maxRedirections = $maxRedirections;
  }


  private function get($url, $method, $parameters, $cookies, $referer)
  {
    $context = null;

    $http = array();
    $http['follow_location'] = false;
    $http['user_agent'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/45.0.2454.101 Chrome/45.0.2454.101 Safari/537.36';
    $http['method'] = $method;

    $headers = array();
    if ($method == 'POST') $headers[] = 'Content-type: application/x-www-form-urlencoded';
    $setCookies = array();
    foreach ($cookies AS $cookieKey => $cookieValue)
    {
      $setCookies[] = $cookieKey.'='.$cookieValue;
    }
    $headers[] = 'Cookie: '.implode('; ', $setCookies);
    if ($referer) $headers[] = 'Referer: '.$referer;

    $http['header'] = implode("\r\n", $headers);

    if ($method == 'POST') $http['content'] = http_build_query($parameters);
    $opts['http'] = $http;


    $context  = stream_context_create($opts);

    if ($method == 'GET' && !empty($parameters))
    {
      $urlToGo = $url.(strpos($url, '?') !== false ? '&' : '?').http_build_query($parameters);
    }
    else
    {
      $urlToGo = $url;
    }

    $result = file_get_contents($urlToGo, false, $context);
    $headers = $this->parseHeaders($http_response_header);

    //Merge cookies from this request with set cookies
    $headers['cookies'] = array_merge($cookies, $headers['cookies']);

    if (!is_null($headers['location']) && $this->redirectionsCount < $this->maxRedirections)
    {
      //We are redirecting, lets set cookies if any and go to new location
      $this->redirectionsCount++;
      return $this->get($headers['location'], $method, array(), $headers['cookies'], $urlToGo);
    }

    return array($result, $headers, $url);
  }

  private function parseHeaders($headers)
  {
    $startParse =  false;
    $all = array();
    $cookies = array();
    $location = null;
    foreach ($headers AS $header)
    {
      // Parse cookies
      $matches = array();
      if (preg_match('/^Set-Cookie:\s+(\S+)=(\S+)(?:$|;)/i', $header, $matches))
      {
        list($m, $cookieKey, $cookieValue) = $matches;
        $cookies[$cookieKey] = $cookieValue;
      }

      // Parse Location
      $matches = array();
      if (preg_match('/^^Location:\s+(\S+)$/i', $header, $matches))
      {
        list($m, $locationFound) = $matches;
        $location = $locationFound;
      }

      //Start parsing after HTTP code 200
      if (preg_match('/^HTTP\/\d\.\d\s200\sOK$/i', $header))
      {
        $startParse = true;
      }
      if ($startParse)
      {
        $matches = array();
        if (preg_match('/^(\S+):\s(.+)$/i', $header, $matches))
        {
          list($m, $headerKey, $headerValue) = $matches;
          $all[$headerKey] = $headerValue;
        }
      }
    }
    return array('all' => $all, 'cookies' => $cookies, 'location' => $location);
  }

  public function call()
  {
    return $this->get($this->url, $this->method, $this->parameters, $this->cookies, $this->url);
  }

  public function debug($data = null)
  {
    if (is_null($data))
    {
      $data = $this->call();
    }

    echo  '<pre>';
    list($data, $headers, $lastUrl) = $data;
    echo htmlspecialchars($data);
    print_r($headers);
    print_r($lastUrl);
    echo  '</pre>';
  }
}
