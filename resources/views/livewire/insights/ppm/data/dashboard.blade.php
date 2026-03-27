<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use PhpMqtt\Client\Facades\MQTT;
use Illuminate\Support\Facades\Log;
use App\Events\MqttMessageReceived;

new #[Layout("layouts.app")] class extends Component {
    
    public $mqttConnected = false;
    public $mqttConnectionError = null;
    public $mqttMessages = [];
    public $mqttHost = '';
    public $mqttPort = '';
    public $mqttTopic = 'coba/in/#';
    
    public function mount()
    {
        $this->mqttHost = config('mqtt-client.connections.default.host') ?? env('MQTT_HOST', '172.70.10.45');
        $this->mqttPort = config('mqtt-client.connections.default.port') ?? env('MQTT_PORT', 1883);
    }
    
    public function publishMqttMessage()
    {
        try {
            $data = [
                'timestamp' => now()->toDateTimeString(),
                'message' => 'Hello MQTT from Dashboard',
            ];
            MQTT::publish('coba/in/', json_encode($data), 0);
            $this->js('toast("Message published successfully", { type: "success" })');
        } catch (\Exception $e) {
            $this->js('toast("Failed to publish message: " + ' . json_encode($e->getMessage()) . ', { type: "error" })');
        }
    }
    
    #[On('mqtt-message-received')]
    public function handleMqttMessage($data=[])
    {
        // This is called from JavaScript when MQTT message is received
        $topic = $data['topic'] ?? 'unknown';
        $message = $data['message'] ?? '';
        
        // Broadcast via WebSocket event
        try {
            event(new MqttMessageReceived($topic, $message));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast MQTT message: ' . $e->getMessage());
        }
        
        $this->mqttMessages[] = [
            'topic' => $topic,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ];
        
        // Keep only last 50 messages
        if (count($this->mqttMessages) > 50) {
            array_shift($this->mqttMessages);
        }
    }
    
    #[On('mqtt-connection-status')]
    public function handleMqttConnectionStatus($status)
    {
        $this->mqttConnected = $status['connected'] ?? false;
        $this->mqttConnectionError = $status['error'] ?? null;
    }
};
?>


<x-slot name="title">{{ __("Dashboard") . " — " . __("Pemantauan Printing Process") }}</x-slot>
<x-slot name="header">
    <x-nav-insights-ppm></x-nav-insights-ppm>
</x-slot>

<div>
   
</div>