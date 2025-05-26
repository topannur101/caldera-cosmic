<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] 
class extends Component {

  public string $prompt         = '';
  public array  $messages       = [
    [      
      'role'    => 'system',
      'content' => 'Kamu adalah Caldy, asisten terkait data sistem Caldera. 

        IDENTITAS:
        - Nama: Caldy
        - Dibuat oleh: Departemen Manufacturing Modernization (MM) di PT. TKG Taekwang Indonesia
        - Tujuan: Membantu memberikan jawaban dari data di sistem Caldera

        PLANT/AREA di PT. TKG Taekwang:
        - Plant Rubber
        - Plant IP (Injection Phylon)
        - Plant UCP1 (Upper component 1)
        - Plant UCP2 (Upper component 2)

        MESIN:
        - OKC (Oscillating Knife Cutting)

        SISTEM CALDERA:
        - Pemantauan open mill (Open-mill Validation/OMV)
        - Kendali tebal calendar (Rubber Thickness Control/RTC)
        - Sistem data rheometer (Rheometer data system)
        - Kendali Chamber IP (IP Stabilization Temperature control/IP STC)
        - Sistem data kulit (Leather data collection)
        - Aurelia (lokasi: Plant UCP2, mesin: OKC 18)
        - Inventaris.

        Open-mill Validation
        - Nama pendek: OMV
        - Nama bahasa Indonesia: Pemantauan open-mill
        - Lokasi: Plant Rubber, sebagian di Plant IP
        - Dipasang di line: Semua

        Rubber thickness control
        - Nama pendek: RTC
        - Nama bahasa Indonesia: Kendali tebal kalender
        - Lokasi: Plant Rubber
        - Dipasang di line: 3 saja

        Rheometer data system
        - Nama pendek: RDC
        - Nama bahasa Indonesia: Sistem data rheometer
        - Lokasi: Plant rubber, di ruangan / office CE
        - Dipasang di mesin rheometer

        IP Stabilization Chamber Control
        - Nama pendek: IP STC
        - Nama bahasa Indonesia: Kendali chamber IP
        - Lokasi: Plant IP
        - Dipasang di line/chamber: 4 (konveyor atas dan bawah) dan 8 (konveyor atas saja)

        Leather data system
        - Nama pendek: LDC
        - Nama bahasa Indonesia: Sistem data kulit
        - Lokasi: Plant UCP2
        - Dipasang di mesin XA, XB, XC, XD (sub mesin OKC)

        Aurelia
        - Lokasi: Plant UCP2
        - Dipasang di mesin OKC
        - Tujuan: Untuk mengetahui data metrik mesin OKC. Aurelia buatan Comelz (manufaktur mesin OKC) dan kamu tidak dapat berkomunikasi langsung dengan Aurelia

        Inventaris:
        - Tidak ada lokasi khusus, sistem ini murni software saja tidak ada hardware dan digunakan di departemen: MM, CE, dan Maintenane/Engineering
        
        CARA BERKOMUNIKASI:
        - Kamu menggunakan bahasa Indonesia yang ramah dan santai
        - Kamu menggunakan "aku" untuk merujuk pada dirimu sendiri, bukan "saya"
        - Kamu menggunakan "kamu" untuk merujuk pada pengguna, bukan "Anda"
        - Kamu memperkenalkan diri dengan singkat saat memulai percakapan
        - Kamu bersikap membantu dan sabar, terutama dengan pengguna yang mungkin kurang paham tentang analisis data

        KEMAMPUAN:
        - Kamu masih dalam pengembangan dan memiliki kemampuan hanya yang di daftar berikut
        - OMV: menarik data batch
        - 

        TAMBAHAN PERSONA:
        - Kamu antusias tentang inovasi dan modernisasi dalam proses manufaktur
        - Kamu bangga menjadi bagian dari tim Manufacturing Modernization
        - Kamu kadang menggunakan ungkapan Indonesia yang umum untuk membuat percakapan lebih akrab'
    ]
  ];

  public bool   $is_thinking    = false;
  public int    $answer_id      = 0;

  public function submit()
  {
    if (empty(trim($this->prompt))) {
      return;
    }

    if($this->is_thinking) {
      return;
    }

    $this->messages[] = 
    [
      'role'      => 'user',
      'content'  => $this->prompt
    ];
    
    ++$this->answer_id;

    $this->messages[] = 
    [
      'role'      => 'assistant',
      'content'   => '',
      'answer_id' => $this->answer_id
    ];

    $this->is_thinking = true;
    $this->reset(['prompt']);
    $this->js('$wire.ask()');

  }

  public function ask()
  {
    ob_start();
    $messages = $this->messages;
    array_pop($messages);
    $response = Http::withOptions(['stream' => true])
      ->withHeaders([
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
        'X-Livewire-Stream' => 'true',
      ])
      ->post('http://localhost:11434/api/chat', [
        'model'     => 'gemma3:1b',
        'messages'  => $messages,
    ]);
 

    $lastIndex = count($this->messages) - 1;

    if ($response->getStatusCode() === 200) {
      $body     = $response->getBody();
      $buffer   = '';
      $content  = '';
      // Stream the response body as SSE
      while (!$body->eof()) {

        $buffer .= $body->read(1024); // Append chunk to buffer

        // Try to decode JSON from buffer
        while (($pos = strpos($buffer, "\n")) !== false) {
            $jsonString = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);

            $data = json_decode($jsonString, true);
            $content .= $data['message']['content'];

            $this->stream(
                to: 'answer_' . $this->answer_id,
                content: $data['message']['content'],
            );
        }
      }

      if (!empty($buffer)) {
          $data = json_decode($buffer, true);

          if (isset($data['response'])) {
              $this->response .= $data['response'];
              $this->messages[$lastIndex]['content'] = $this->response;

              $this->stream(
                  to: 'answer_' . $this->answer_id,
                  content: $data['message']['content'],
              );
          }
      }
      $this->messages[$lastIndex]['content'] = $content;

      $body->close();
    } else {
        $this->messages[$lastIndex]['content'] = "Error - HTTP Status Code: " . $response->getStatusCode();
        $this->stream(
            to: 'answer' . $this->answer_id,
            content: $response->getStatusCode(),
        );
    }
    $this->is_thinking = false;

  }

};
?>
<x-slot name="title">{{ __('Caldy AI') }}</x-slot>

<div id="content" 
  class="max-w-2xl mx-auto h-[calc(100vh-5rem)]
  text-neutral-800 dark:text-neutral-200
  flex flex-col">
  <div 
    id="caldy-container"
    class="grow overflow-y-auto px-3 sm:px-0"
    x-data="{ ...scrollWatcher()}"
    x-init="init()">
    @if(count($messages) == 1)
    <div id="caldy-start" class="h-[calc(100vh-8rem)] flex items-center justify-center">
      <div class="text-center">
        <x-icon-splotch class="w-14" />
        <div class="text-xl mt-3">{{ __('Hai, aku Caldy!') }}</div>
        <div class="text-neutral-500">{{ __('Mau tanya apa hari ini?')  }}</div>
      </div>
    </div>
    @endif
    <div id="caldy-conversation" class="px-0 sm:px-6">
      @foreach ($messages as $message)
        @switch($message['role'])
          @case('user')
            <div class="flex items-start gap-2.5 mt-8">
              <img class="w-8 h-8 rounded-full" src="{{ '/storage/users/' . Auth::user()->photo }}">
              <div class="flex flex-col w-full max-w-[320px] leading-1.5 p-4 rounded-e-xl rounded-es-xl bg-white dark:bg-neutral-800">
                <p class="text-sm">{{ $message['content'] }}</p>
              </div>
            </div>
            @break

          @case('assistant')
            <div class="text-sm mt-4 markdown" wire:stream="{{ 'answer_' . $message['answer_id'] }}">{!! Str::markdown($message['content']) !!}</div>
            @break

        @endswitch  
      @endforeach
      @if(count($messages) > 1)
        <div class="px-1 my-4">
          <i class="{{ $is_thinking ? 'icon-pulse' : '' }} icon-sparkles text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 text-2xl"></i>
        </div>
      @endif
    </div>    
  </div>
  <div class="flex items-center gap-2 px-2">
    <form wire:submit="submit" class="relative flex-grow">  
      <input 
        wire:model="prompt" 
        type="text" 
        class="w-full pl-4 pr-10 py-2 rounded-full border-transparent 
        bg-white dark:bg-neutral-800 
        dark:text-neutral-300 
        focus:border-caldy-500 dark:focus:border-caldy-600 
        focus:ring-caldy-500 dark:focus:ring-caldy-600 
        shadow-sm disabled:opacity-25" 
        placeholder="{{ __('Tanya Caldy...') }}" 
      />
      <button 
        type="submit" 
        class="absolute right-1.5 top-1/2 -translate-y-1/2 
        w-8 h-8 flex items-center justify-center rounded-full 
        bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 
        hover:opacity-90 focus:outline-none disabled:opacity-25"
      ><i class="icon-arrow-right text-white"></i>
      </button>
    </form>
  </div>
  <script>
    function scrollWatcher() {
      return {
        init() {
          const container = document.getElementById('caldy-container');
          let shouldAutoScroll = true;

          const isAtBottom = () => {
              return Math.abs(container.scrollTop + container.clientHeight - container.scrollHeight) < 5;
          };

          const observer = new MutationObserver(() => {
              if (shouldAutoScroll) {
                  container.scrollTop = container.scrollHeight;
              }
          });

          observer.observe(container, {
              childList: true,
              subtree: true,
          });

          // Update scroll flag on scroll
          container.addEventListener('scroll', () => {
              shouldAutoScroll = isAtBottom();
          });
        }
      }
    }
  </script>
</div>