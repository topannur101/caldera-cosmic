<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] 
class extends Component {

  public $prompt = '';
  public $response = '';
  public $model = '';
  public $models = ['gemma3:1b'];
  public $promptAppend = '. Make sure your response is in Markdown format';

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
              'prompt' => $this->prompt . $this->promptAppend
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

                      $this->stream(
                          to: 'response',
                          content: Str::markdown($this->response),
                          replace: true
                      );
                  }
              }
          }

          if (!empty($buffer)) {
              $data = json_decode($buffer, true);

              if (isset($data['response'])) {
                  $this->response .= $data['response'];
                  $this->stream(
                      to: 'response',
                      content: Str::markdown(this->response),
                      replace: true
                  );
              }
          }

          $body->close();
      } else {
          echo "data: Error - HTTP Status Code: " . $response->getStatusCode() . "\n\n";
          ob_flush();
          flush();
      }
  }


};
?>
<x-slot name="title">{{ __('Caldy AI') }}</x-slot>

<div id="content" class="py-12 max-w-2xl mx-auto sm:px-3 text-neutral-800 dark:text-neutral-200">
  <div class="bg-white dark:bg-neutral-800 shadow sm:rounded-lg overflow-hidden">
    <div class="text-center w-72 py-20 mx-auto">
      <i class="fa fa-fw text-5xl fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>
      <div class="text-xl mt-3">{{ __('Hai, aku Caldy!') }}</div>
      <div class="text-neutral-500">{{ __('Mau tanya apa hari ini?')  }}</div>
    </div>

    <div class="overflow-hidden relative">
        <div x-data="{ response: @entangle('response'), showResponse: false }" class="flex overflow-y-scroll flex-col flex-1 justify-between items-center pt-5 pb-24 mx-auto" style="overflow-y:scroll">
            <div x-show="showResponse" class="p-5 w-full h-auto rounded-lg border ">
                <div wire:stream="response"></div>
                <div>{!! Str::markdown($response) !!}</div>
            </div>
            <div class="flex fixed bottom-0 justify-center items-center w-full">
                <div class="absolute bottom-0 left-0 z-20 w-full h-32"></div>
                <div class="relative z-30 w-full max-w-2xl -translate-y-5">
                    <label for="aiPrompt" class="sr-only">ai prompt</label>
                    <i class="fa fa-fw me-2 fa-splotch text-transparent bg-clip-text bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-500"></i>
                    <input wire:model="prompt" @keyup.enter="showResponse=true" wire:keydown.enter="submit" type="text" class="px-2 py-2.5 pr-24 pl-10 w-full text-sm rounded-md border border-outline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2  disabled:cursor-not-allowed disabled:opacity-75 " name="prompt" placeholder="Ask AI ..." />
                    <button wire:click="submit" x-on:click="showResponse=true" type="button" class="absolute right-3 top-1/2 px-2 py-1 text-xs tracking-wide  rounded-md transition -translate-y-1/2 cursor-pointer hover:opacity-75 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 active:opacity-100 active:outline-offset-0">
                        <span wire:loading.class="invisible">Generate</span>
                        <span wire:loading.flex class="flex absolute top-0 left-0 justify-center items-center w-full h-full">
                            <svg class="w-3 h-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>
      </div>

  </div>
</div>
