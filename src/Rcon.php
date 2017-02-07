<?php
namespace MinecraftRcon;

/**
 * Class Rcon
 * @package Minecraft
 */
class Rcon
{
    /** @var string */
    private $host = '127.0.0.1';
    /** @var integer */
    private $port = 25575;
    /** @var string */
    private $password;
    /** @var int */
    private $timeout = 3;

    private $socket;

    private $authorized;
    private $lastResponse;

    const PACKET_AUTHORIZE = 5;
    const PACKET_COMMAND = 6;

    const SERVERDATA_AUTH = 3;
    const SERVERDATA_AUTH_RESPONSE = 2;
    const SERVERDATA_EXECCOMMAND = 2;
    const SERVERDATA_RESPONSE_VALUE = 0;

    const RESPONSE_RAW = 0;
    const RESPONSE_FORMATTED = 1;

    /**
     * Connect to a server.
     *
     * @return boolean
     */
    public function connect()
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->lastResponse = $errstr;
            return false;
        }

        //set timeout
        stream_set_timeout($this->socket, 3, 0);

        // check authorization
        if ($this->authorize()) {
            return true;
        }

        return false;
    }

    /**
     * Disconnect from server.
     *
     * @return void
     */
    public function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    /**
     * Send a command to the connected server.
     *
     * @param string $command
     *
     * @return boolean|mixed
     */
    public function sendCommand($command)
    {
        if (!$this->isConnected()) {
            return false;
        }

        // send command packet
        $this->writePacket(self::PACKET_COMMAND, self::SERVERDATA_EXECCOMMAND, $command);

        // get response
        $responsePacket = $this->readPacket();
        if ($responsePacket['id'] == self::PACKET_COMMAND) {
            if ($responsePacket['type'] == self::SERVERDATA_RESPONSE_VALUE) {
                $this->lastResponse = $responsePacket['body'];

                return $responsePacket['body'];
            }
        }

        return false;
    }

    /**
     * Log into the server with the given credentials.
     *
     * @return boolean
     */
    private function authorize()
    {
        $this->writePacket(self::PACKET_AUTHORIZE, self::SERVERDATA_AUTH, $this->password);
        $responsePacket = $this->readPacket();

        if ($responsePacket['type'] == self::SERVERDATA_AUTH_RESPONSE) {
            if ($responsePacket['id'] == self::PACKET_AUTHORIZE) {
                $this->authorized = true;

                return true;
            }
        }

        $this->disconnect();
        return false;
    }

    /**
     * Writes a packet to the socket stream.
     *
     * @param $packetId
     * @param $packetType
     * @param $packetBody
     *
     * @return void
     */
    private function writePacket($packetId, $packetType, $packetBody)
    {
        /*
        Size			32-bit little-endian Signed Integer	 	Varies, see below.
        ID				32-bit little-endian Signed Integer		Varies, see below.
        Type	        32-bit little-endian Signed Integer		Varies, see below.
        Body		    Null-terminated ASCII String			Varies, see below.
        Empty String    Null-terminated ASCII String			0x00
        */

        //create packet
        $packet = pack("VV", $packetId, $packetType);
        $packet = $packet . $packetBody . "\x00";
        $packet = $packet . "\x00";

        // get packet size.
        $packetSize = strlen($packet);

        // attach size to packet.
        $packet = pack("V", $packetSize) . $packet;

        // write packet.
        fwrite($this->socket, $packet, strlen($packet));
    }

    /**
     * Read a packet from the socket stream.
     *
     * @return array
     */
    private function readPacket()
    {
        //get packet size.
        $sizeData = fread($this->socket, 4);
        $sizePack = unpack("V1size", $sizeData);
        $size = $sizePack['size'];

        // if size is > 4096, the response will be in multiple packets.
        // this needs to be address. get more info about multi-packet responses
        // from the RCON protocol specification at
        // https://developer.valvesoftware.com/wiki/Source_RCON_Protocol
        // currently, this script does not support multi-packet responses.

        $packetData = fread($this->socket, $size);
        $packetPack = unpack("V1id/V1type/a*body", $packetData);

        return $packetPack;
    }

    /**
     * True if socket is connected and authorized.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->authorized;
    }

    /**
     * @param string $host
     * @return Rcon
     */
    public function setHost($host)
    {
        $this->host = gethostbyname($host);
        return $this;
    }

    /**
     * @param int $port
     * @return Rcon
     */
    public function setPort($port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @param string $password
     * @return Rcon
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get the latest raw response from the server.
     *
     * @return string
     */
    private function getRawResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Get formatted response from server
     * @return string
     */
    private function getFormattedResponse()
    {
        return preg_replace("/§./", "", $this->getRawResponse());
    }

    /**
     * @param $type
     * @return null|string
     */
    public function getResponse($type = self::RESPONSE_RAW)
    {
        switch ($type) {
            case self::RESPONSE_RAW:
                return $this->getRawResponse();
            case self::RESPONSE_FORMATTED:
                return $this->getFormattedResponse();
        }

        return null;
    }
}
