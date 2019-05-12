<?php
namespace Rindow\Module\Stomp;

class Frame
{
	protected $command;
	protected $headers = array();
	protected $body;
	
    public function __construct($command=null, array $headers=null, $body=null)
	{
		if($command)
			$this->command = $command;
		if($headers)
			$this->headers = $headers;
		if($body)
			$this->body = $body;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    public function getCommand()
    {
    	return $this->command;
    }

    public function addHeaders(array $headers=null)
    {
    	if($headers===null)
    		return;
    	$this->headers = array_merge($this->headers,$headers);
    }

    public function addHeader($name,$value)
    {
    	$this->headers[$name] = $value;
    }

    public function getHeader($name)
    {
    	if(isset($this->headers[$name]))
    	    return $this->headers[$name];
    	else
    		return null;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function setBody($value)
    {
    	$this->body = $value;
    }

    public function getBody()
    {
    	return $this->body;
    }
}