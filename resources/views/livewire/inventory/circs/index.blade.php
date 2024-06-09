<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;

use App\Inventory;
use Carbon\Carbon;
use App\Models\Pref;
use App\Models\User;
use App\Models\InvArea;
use App\Models\InvCurr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

new #[Layout('layouts.app')] 
class extends Component {

  public $ids = [];
  #[Url]
  public $q = '';
  #[Url]
  public $status = ['pending', 'approved'];
  #[Url]
  public $user = '';
  #[Url]
  public $qdirs = ['deposit', 'withdrawal', 'capture'];
  #[Url]
  public $start_at = '';
  #[Url]
  public $end_at = '';
  #[Url]
  public $area_ids = [];
  public $area_ids_clean = [];
  #[Url]
  public $sort = 'updated';
  public $areas;
  public $inv_curr;
  public $perPage = 10;

  public function mount()
    {
        $user = User::find(Auth::user()->id);
        $this->areas = $user->id === 1 ? InvArea::all() : $user->inv_areas;
                
        $pref = Pref::where('user_id', Auth::user()->id)->where('name', 'inv-circs')->first();
        $pref = json_decode($pref->data ?? '{}', true);
        $this->q        = isset($pref['q'])         ? $pref['q']        : '';
        $this->status   = isset($pref['status'])    ? $pref['status']   : ['pending', 'approved'];
        $this->user     = isset($pref['user'])      ? $pref['user']     : '';
        $this->qdirs    = isset($pref['qdirs'])     ? $pref['qdirs']    : ['deposit', 'withdrawal', 'capture'];
        $this->start_at = isset($pref['start_at'])  ? $pref['start_at'] : Carbon::now()->startOfMonth()->format('Y-m-d');;
        $this->end_at   = isset($pref['end_at'])    ? $pref['end_at']   : Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->area_ids = isset($pref['area_ids'])  ? $pref['area_ids'] : $this->areas->pluck('id')->toArray();
        $this->sort     = isset($pref['sort'])      ? $pref['sort']     : 'updated';

        $this->inv_curr = InvCurr::find(1);
    }

    public function setToday()
    {
        $this->start_at = Carbon::now()->startOfDay()->format('Y-m-d');
        $this->end_at = Carbon::now()->endOfDay()->format('Y-m-d');
    }

    public function setYesterday()
    {
        $this->start_at = Carbon::yesterday()->startOfDay()->format('Y-m-d');
        $this->end_at = Carbon::yesterday()->endOfDay()->format('Y-m-d');
    }

    public function setThisMonth()
    {
        $this->start_at = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->end_at = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

    public function setLastMonth()
    {
        $this->start_at = Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $this->end_at = Carbon::now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
    }

    public function resetCircs()
    {
        $this->area_ids = $this->areas->pluck('id')->toArray();
        
        $this->start_at = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->end_at   = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->reset('q', 'status', 'user', 'qdirs');
    }

    public function loadMore()
    {
        $this->perPage += 10;
    }

    #[On('circ-updated')]
    public function clearIds()
    {
        $this->reset('ids');
    }

    public function print()
    {
        return redirect(route('inventory.circs.print'))->with('ids', $this->ids);
    }

    public function download()
    {
      //
    }

}

?>

<x-slot name="title">{{ __('Sirkulasi') . ' — ' . __('Inventaris') }}</x-slot>

<x-slot name="header">
  <x-nav-inventory></x-nav-inventory>
</x-slot>

<div id="content" class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8 text-neutral-800 dark:text-neutral-200 grid gap-1">
   Hehe
</div>
