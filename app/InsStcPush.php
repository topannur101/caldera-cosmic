<?php

namespace App;

use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\WriteMultipleRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\WriteSingleCoilRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Types;
use Exception;
use InvalidArgumentException;

class InsStcPush
{
    /**
     * Send section_svp, section_hb, or zone_hb to HMI through Modbus TCP
     */
    public function send(string $type, string $ipAddress, string $position, array $values, int $port = 503, int $unitID = 1)
    {
        $typeConfigs = [
            'apply_svw' => [
                'valueCount' => 1,
                'startAddresses' => [
                    'upper' => 322,
                    'lower' => 312
                ],
                'function' => 'singleCoil'
            ],
            'section_svp' => [
                'valueCount' => 8,
                'startAddresses' => [
                    'upper' => 230,
                    'lower' => 130
                ],
                'function' => 'multipleRegisters'
            ],
            'section_hb' => [
                'valueCount' => 8,
                'startAddresses' => [
                    'upper' => 270,
                    'lower' => 170
                ],
                'function' => 'multipleRegisters'
            ],
            'zone_hb' => [
                'valueCount' => 4,
                'startAddresses' => [
                    'upper' => 260,
                    'lower' => 160
                ],
                'function' => 'multipleRegisters'
            ],
            'chart_hb' => [
                'valueCount' => 60,
                'startAddresses' => [
                    'upper' => 1002,
                    'lower' => 2002
                ],
                'function' => 'multipleRegisters'
            ]
        ];

        if (strpos($ipAddress, '127.') === 0) {
            throw new InvalidArgumentException("The IP is a loopback address");
        }
    
        if (!isset($typeConfigs[$type])) {
            throw new InvalidArgumentException("Invalid type: $type");
        }
    
        $config = $typeConfigs[$type];

        if (!in_array($position, ['upper', 'lower'])) {
            throw new InvalidArgumentException("Invalid position: $position");
        }
    
        if (count($values) !== $config['valueCount']) {
            throw new InvalidArgumentException("Invalid number of values for type: $type");
        }

        $startAddress = $config['startAddresses'][$position];
        $connection = BinaryStreamConnection::getBuilder()
            ->setPort($port)
            ->setHost($ipAddress)
            ->build();

        try {
            $connection->connect();

            if ($config['function'] === 'singleCoil') {
                $coil = $values[0];
                $packet = new WriteSingleCoilRequest($startAddress, $coil, $unitID);
            } else {
                $registers = array_map(function($value) {
                    $intValue = (int)$value;
                    if ($intValue < 30 || $intValue > 90) {
                        throw new InvalidArgumentException(
                            __('Nilai temperatur berada di luar jangkauan (30-90)')
                        );
                    }
                    return Types::toInt16($intValue);
                }, $values);
                $packet = new WriteMultipleRegistersRequest($startAddress, $registers, $unitID);
            }

            $binaryData = $connection->sendAndReceive($packet);

            if ($config['function'] === 'singleCoil') {
                $response = ResponseFactory::parseResponseOrThrow($binaryData);
                return $response->isCoil();
            } else {
                $response = ResponseFactory::parseResponseOrThrow($binaryData);
                return $response;
            }

        } catch (Exception $exception) {
            throw $exception;

        } finally {
            $connection->close();
        }
    }
}