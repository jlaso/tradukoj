<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso>
 */

namespace JLaso\TranslationsBundle\Model;


class SocketClient
{

    // uniqid for the client
    protected $id;

    // version of db the client has
    protected $version;

    protected $socket;

    function __construct($id, $socket)
    {
        $this->id = $id;
        $this->version = 0;
        $this->socket = $socket;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param mixed $socket
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;
    }

    /**
     * @return mixed
     */
    public function getSocket()
    {
        return $this->socket;
    }



} 