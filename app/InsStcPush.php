<?php

namespace App;

use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersResponse;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Types;
use Exception;

class InsStcPush
{
    /**
     * Send SVP values to HMI via Modbus TCP
     *
     * @param string $ipAddress IP address of the HMI
     * @param array $svpValues Array of SVP values from index 0 to 7
     * @param int $port Port number (default 502 for Modbus TCP)
     * @return WriteMultipleRegistersResponse
     * @throws Exception
     */
    public function send(string $ipAddress, string $position, array $svpValues, int $port = 503, int $unitID = 1): WriteMultipleRegistersResponse
    {
        // Validate input array
        if (count($svpValues) !== 8) {
            throw new \InvalidArgumentException("SVP values array must contain exactly 8 values");
        }

        // Validate and prepare registers
        $registers = array_map(function($value) {
            $intValue = (int)$value;
            if ($intValue < 20 || $intValue > 90) {
                throw new \InvalidArgumentException(
                    "Register value {$intValue} is out of range (20-90)"
                );
            }
            return Types::toInt16($intValue);
        }, $svpValues);

        // Create connection
        $connection = BinaryStreamConnection::getBuilder()
            ->setPort($port)
            ->setHost($ipAddress)
            ->build();

        switch ($position) {
            case 'upper':
                $startAddress = 230;
                break;
            
            case 'lower':
                $startAddress = 130;
                break;
            default:
                throw new \InvalidArgumentException("Position must be either upper or lower.");
        }

        // Create Modbus request
        
        $packet = new WriteMultipleRegistersRequest($startAddress, $registers, $unitID);

        try {
            // Send and receive
            $binaryData = $connection->connect()->sendAndReceive($packet);
            
            // Parse response
            /** @var WriteMultipleRegistersResponse $response */
            $response = ResponseFactory::parseResponseOrThrow($binaryData);
            
            return $response;
        } catch (Exception $exception) {
            throw $exception;
        } finally {
            $connection->close();
        }
    }
}