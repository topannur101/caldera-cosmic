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
     * Send section_svp, section_hb, or zone_hb to HMI through Modbus TCP
     */
    public function send(string $type, string $ipAddress, string $position, array $values, int $port = 503, int $unitID = 1): WriteMultipleRegistersResponse
    {
        // Validate type
        $typeConfigs = [
            'section_svp' => [
                'valueCount' => 8,
                'startAddresses' => [
                    'upper' => 230,
                    'lower' => 130
                ]
            ],
            'section_hb' => [
                'valueCount' => 8,
                'startAddresses' => [
                    'upper' => 270,
                    'lower' => 170
                ]
            ],
            'zone_hb' => [
                'valueCount' => 4,
                'startAddresses' => [
                    'upper' => 260,
                    'lower' => 160
                ]
            ]
        ];
    
        // Check if type is valid
        if (!isset($typeConfigs[$type])) {
            throw new \InvalidArgumentException(__('Tipe yang diberikan tidak sah.'));
        }
    
        $config = $typeConfigs[$type];
    
        // Validate position
        if (!in_array($position, ['upper', 'lower'])) {
            throw new \InvalidArgumentException(__('Posisi harus berupa upper atau lower.'));
        }
    
        // Validate input array count
        if (count($values) !== $config['valueCount']) {
            throw new \InvalidArgumentException(__('Jumlah nilai kurang dari persyaratan'));
        }
    
        // Validate and prepare registers
        $registers = array_map(function($value) {
            $intValue = (int)$value;
            if ($intValue < 20 || $intValue > 90) {
                throw new \InvalidArgumentException(
                    __('Nilai temperatur berada di luar jangkauan (20-90)')
                );
            }
            return Types::toInt16($intValue);
        }, $values);
    
        // Create connection
        $connection = BinaryStreamConnection::getBuilder()
            ->setPort($port)
            ->setHost($ipAddress)
            ->build();
    
        // Get start address based on type and position
        $startAddress = $config['startAddresses'][$position];
    
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