<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] 
class extends Component {

  public $prompt = '';
  public $response = '';
  public $model = '';
  public $models = ['gemma3:1b'];
  public $promptAppend = '. Make sure your response is in Markdown format';
  public $messages = [];

  public function mount(){
      // $this->listModels();
      // if(!$this->models[0]){
      //     die("Be sure to add a model to Ollama before running");
      //     return;
      // }
      // $this->model == $this->models[0];
  }

  public function modelUpdated(){
      $this->response = '';
  }

  // public function listModels()
  // {
  //     $command = 'ollama list';
  //     $output = [];
  //     $returnVar = null;
  //     exec($command, $output, $returnVar);

  //     if ($returnVar === 0) {
  //         $this->models = $output;
  //     } else {
  //         $this->models = ['Error: Unable to fetch models'];
  //     }

  //     $modelsFiltered = [];
  //     foreach($this->models as $index => $model){
  //         if($index != 0){
  //             $modelParts = explode(':', $model);
  //             array_push($modelsFiltered, $modelParts[0]);
  //         }
  //     }
  //     $this->models = $modelsFiltered;
  // }

  public function submit()
  {
      if (empty(trim($this->prompt))) {
          return;
      }
      
      // Add user message to chat
      $this->messages[] = [
          'type' => 'user',
          'content' => $this->prompt
      ];
      
      // Add empty AI message that will be populated
      $messageIndex = count($this->messages);
      $this->messages[] = [
          'type' => 'ai',
          'content' => ''
      ];
      
      $this->response = '';
      $userPrompt = $this->prompt;
      $this->prompt = '';
      
      ob_start();
      $client = new GuzzleHttp\Client(); 
      $response = Http::withOptions(['stream' => true])
          ->withHeaders([
              'Content-Type' => 'text/event-stream',
              'Cache-Control' => 'no-cache',
              'X-Accel-Buffering' => 'no',
              'X-Livewire-Stream' => 'true',
          ])
          ->post('http://localhost:11434/api/generate', [
              'model' => 'gemma3:1b',
              'prompt' => $userPrompt . $this->promptAppend
          ]);

      if ($response->getStatusCode() === 200) {
          $body = $response->getBody();
          $buffer = '';
          // Stream the response body as SSE
          while (!$body->eof()) {

              $buffer .= $body->read(1024); // Append chunk to buffer

              // Try to decode JSON from buffer
              while (($pos = strpos($buffer, "\n")) !== false) {
                  $jsonString = substr($buffer, 0, $pos);
                  $buffer = substr($buffer, $pos + 1);

                  $data = json_decode($jsonString, true);

                  if (isset($data['response'])) {
                      $this->response .= $data['response'];
                      $this->messages[$messageIndex]['content'] = $this->response;

                      $this->stream(
                          to: 'chat-messages',
                          content: $this->renderMessages(),
                          replace: true
                      );
                  }
              }
          }

          if (!empty($buffer)) {
              $data = json_decode($buffer, true);

              if (isset($data['response'])) {
                  $this->response .= $data['response'];
                  $this->messages[$messageIndex]['content'] = $this->response;
                  $this->stream(
                      to: 'chat-messages',
                      content: $this->renderMessages(),
                      replace: true
                  );
              }
          }

          $body->close();
      } else {
          $this->messages[$messageIndex]['content'] = "Error - HTTP Status Code: " . $response->getStatusCode();
          $this->stream(
              to: 'chat-messages',
              content: $this->renderMessages(),
              replace: true
          );
      }
  }

  public function renderMessages()
  {
      $output = '';
      foreach ($this->messages as $message) {
          if ($message['type'] === 'user') {
              $userPhoto = Auth::user()->photo ;
              $output .= '
              <div class="flex items-start gap-4 mb-4">
                  <div class="flex-shrink-0">
                      <img src="/storage/users/' . $userPhoto . '" alt="User" class="w-8 h-8 rounded-full">
                  </div>
                  <div class="flex-grow bg-blue-50 dark:bg-neutral-700 p-3 rounded-lg max-w-[80%]">
                      <div class="text-sm">' . e($message['content']) . '</div>
                  </div>
              </div>';
          } else {
              $output .= '
              <div class="flex items-start gap-4 mb-4">
                  <div class="flex-shrink-0">
                      <i class="fa fa-fw fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 text-2xl"></i>
                  </div>
                  <div class="flex-grow bg-purple-50 dark:bg-neutral-600 p-3 rounded-lg max-w-[80%]">
                      <div class="text-sm markdown">' . Str::markdown($message['content']) . '</div>
                  </div>
              </div>';
          }
      }
      return $output;
  }
};
?>
<x-slot name="title">{{ __('Caldy AI') }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
  <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
    <div x-data="{ messages: @entangle('messages'), scrollToBottom() { this.$nextTick(() => { const container = document.getElementById('chat-container'); if (container) container.scrollTop = container.scrollHeight; });} }" class="relative">
      <!-- Welcome message shown when no messages -->
      <div x-show="messages.length === 0" class="text-center w-72 py-20 mx-auto">
        <i class="fa fa-fw text-5xl fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>
        <div class="text-xl mt-3">{{ __('Hai, aku Caldy!') }}</div>
        <div class="text-neutral-500">{{ __('Mau tanya apa hari ini?')  }}</div>
      </div>
      
      <!-- Chat messages -->
      <div x-show="messages.length > 0" class="overflow-y-auto p-4" style="max-height: 70vh; min-height: 400px;" id="chat-container" x-init="$watch('messages', () => scrollToBottom())" @messagesUpdated.window="scrollToBottom()">
        <div wire:stream="chat-messages">
          @foreach($messages ?? [] as $message)
            @if($message['type'] === 'user')
              <div class="flex items-start gap-4 mb-4">
                <div class="flex-shrink-0">
                  <img src="{{ '/storage/users/' . Auth::user()->photo ?? 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->name ?? 'User') }}" alt="User" class="w-8 h-8 rounded-full">
                </div>
                <div class="flex-grow bg-blue-50 dark:bg-neutral-700 p-3 rounded-lg max-w-[80%]">
                  <div class="text-sm">{{ $message['content'] }}</div>
                </div>
              </div>
            @else
              <div class="flex items-start gap-4 mb-4">
                <div class="flex-shrink-0">
                  <i class="fa fa-fw fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 text-2xl"></i>
                </div>
                <div class="flex-grow bg-purple-50 dark:bg-neutral-600 p-3 rounded-lg max-w-[80%]">
                  <div class="text-sm markdown">{!! Str::markdown($message['content']) !!}</div>
                </div>
              </div>
            @endif
          @endforeach
        </div>
      </div>
      
      <!-- Input area fixed at bottom -->
      <div class="sticky bottom-0 left-0 w-full bg-white dark:bg-neutral-800 p-4 border-t dark:border-neutral-700">
      @if(Auth::user()->id === 1)  
        <div class="flex items-center gap-2">
          <div class="flex-shrink-0">
            <i class="fa fa-fw fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>
          </div>
          <div class="relative flex-grow">
            
            
            <input 
              wire:model="prompt" 
              wire:keydown.enter="submit" 
              type="text" 
              class="w-full px-4 py-2 text-sm rounded-full border border-outline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-75 dark:bg-neutral-700 dark:border-neutral-600" 
              placeholder="Ask AI..." 
              x-on:keydown.enter="$nextTick(() => document.getElementById('chat-container').scrollTop = document.getElementById('chat-container').scrollHeight)"
            />
            
            <button 
              wire:click="submit" 
              type="button" 
              class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-full bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500 text-white hover:opacity-90 focus:outline-none"
              x-on:click="$nextTick(() => document.getElementById('chat-container').scrollTop = document.getElementById('chat-container').scrollHeight)"
            >
              <span wire:loading.class="hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </span>
              <span wire:loading.flex class="flex items-center justify-center">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </span>
            </button>
          </div>
        </div>
        @else
        <div class="text-sm">
          <i class="fa fa-lock mr-2"></i>{{ __('Akses ke Caldy AI hanya diperbolehkan untuk pengguna tertentu') }}
          <p class="text-neutral-500 mt-2">
            {{  __('Caldy AI sedang dalam tahap pengembangan, jika sudah siap untuk digunakan publik, kami akan beritahu lewat notifikasi.') }}
          </p>
        </div>
          
          @endif
      </div>
      
      <!-- Auto-scroll script -->
      <script>
        document.addEventListener('livewire:init', () => {
          Livewire.hook('message.processed', (message, component) => {
            const chatContainer = document.getElementById('chat-container');
            if (chatContainer) {
              chatContainer.scrollTop = chatContainer.scrollHeight;
            }
          });
        });
      </script>
    </div>
  </div>
</div>