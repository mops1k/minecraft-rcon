<?php
namespace MinecraftRcon;

final class Rcon
{
    private string $host;
    private int $port;
    private ?string $password;
    private int $timeout;

    /**
     * @var false|resource
     */
    private $socket;

    private ?string $lastResponse = null;

    private const PACKET_AUTHORIZE = 5;
    private const PACKET_COMMAND = 6;

    private const SERVER_DATA_AUTH = 3;
    private const SERVER_DATA_AUTH_RESPONSE = 2;
    private const SERVER_DATA_EXECUTE_COMMAND = 2;
    private const SERVER_DATA_RESPONSE_VALUE = 0;

    public const RESPONSE_RAW = 0;
    public const RESPONSE_FORMATTED = 1;

    /**
     * @throws RconAuthorizationException
     * @throws RconConnectionException
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 25575,
        ?string $password = null,
        int $timeout = 3
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;

        $this->connect();
    }

    /**
     * Send a command to the connected server.
     */
    public function send(string $command): self
    {
        // send command packet
        $this->writePacket(self::PACKET_COMMAND, self::SERVER_DATA_EXECUTE_COMMAND, $command);

        // get response
        $responsePacket = $this->readPacket();
        if (((int)$responsePacket['id'] === self::PACKET_COMMAND)
            && (int)$responsePacket['type'] === self::SERVER_DATA_RESPONSE_VALUE) {
            $this->lastResponse = $responsePacket['body'];
        }

        return $this;
    }

    public function getResponse(int $type = self::RESPONSE_RAW): ?string
    {
        switch ($type) {
            case self::RESPONSE_RAW:
                return $this->lastRawResponse();
            case self::RESPONSE_FORMATTED:
                return $this->lastFormattedResponse();
        }

        return null;
    }

    /**
     * Log into the server with the given credentials.
     */
    private function authorize(): bool
    {
        $this->writePacket(self::PACKET_AUTHORIZE, self::SERVER_DATA_AUTH, $this->password ?? '');
        $responsePacket = $this->readPacket();

        if (((int) $responsePacket['type'] === self::SERVER_DATA_AUTH_RESPONSE)
            && (int) $responsePacket['id'] === self::PACKET_AUTHORIZE) {
            return true;
        }

        $this->disconnect();

        return false;
    }

    /**
     * Connect to a server.
     *
     * @throws RconConnectionException|RconAuthorizationException
     */
    private function connect(): void
    {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->lastResponse = $errstr;

            throw new RconConnectionException('Connection failed. Please check connection details.');
        }

        stream_set_timeout($this->socket, 3, 0);

        if ($this->authorize()) {
            return;
        }

        throw new RconAuthorizationException('Authorization failed. Please check credentials.');
    }

    /**
     * Disconnect from server.
     */
    private function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
        }

        $this->socket = false;
    }

    /**
     * Get formatted response from server
     */
    private function lastFormattedResponse(): ?string
    {
        return preg_replace("/§./u", "", $this->lastRawResponse() ?? '');
    }

    /**
     * Get the latest raw response from the server.
     */
    private function lastRawResponse(): ?string
    {
        return $this->lastResponse;
    }

    /**
     * Read a packet from the socket stream.
     */
    private function readPacket(): array
    {
        //get packet size.
        $sizeData = fread($this->socket, 4);
        $sizePack = unpack("V1size", $sizeData);
        $size = $sizePack['size'];

        $packetData = fread($this->socket, $size);

        return unpack("V1id/V1type/a*body", $packetData);
    }

    /**
     * Writes a packet to the socket stream.
     *
     * @param $packetId
     * @param $packetType
     * @param $packetBody
     */
    private function writePacket($packetId, $packetType, $packetBody): void
    {
        /*
        Size			32-bit little-endian Signed Integer	 	Varies, see below.
        ID				32-bit little-endian Signed Integer		Varies, see below.
        Type	        32-bit little-endian Signed Integer		Varies, see below.
        Body		    Null-terminated ASCII String			Varies, see below.
        Empty String    Null-terminated ASCII String			0x00
        */

        $packet = pack("VV", $packetId, $packetType);
        $packet .= $packetBody . "\x00";
        $packet .= "\x00";

        $packetSize = strlen($packet);

        $packet = pack("V", $packetSize) . $packet;

        fwrite($this->socket, $packet, strlen($packet));
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
