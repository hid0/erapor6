<?php

namespace App\Http\Livewire\Pengaturan;

use Livewire\WithPagination;
use Livewire\Component;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Guru;
use App\Models\Peserta_didik;
use App\Models\Role;
use App\Models\Rombongan_belajar;
use App\Models\Ekstrakurikuler;
use App\Models\Pembelajaran;
use Helper;

class Users extends Component
{
    use WithPagination, LivewireAlert;
    protected $paginationTheme = 'bootstrap';
    protected $listeners = ['generatePengguna', 'generatePtk', 'generatePd', 'confirmed', 'confirmReset'];
    public $search = '';
    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function loadPerPage(){
        $this->resetPage();
    }
    public function filterAkses(){
        $this->resetPage();
    }
    public $sortby = 'last_login_at';
    public $sortbydesc = 'DESC';
    public $per_page = 10;
    public $role_id = '';
    public $user_id;
    public $pengguna;
    public $roles = [];
    public $akses;

    public function render()
    {
        $loggedUser = auth()->user();
        return view('livewire.pengaturan.users', [
            'data_user' => User::whereRoleIs(['guru', 'siswa'], session('semester_id'))->where('sekolah_id', $loggedUser->sekolah_id)->orderBy($this->sortby, $this->sortbydesc)
            ->when($this->search, function($ptk) {
                $ptk->where('name', 'ILIKE', '%' . $this->search . '%')
                ->orWhere('nuptk', 'ILIKE', '%' . $this->search . '%')
                ->orWhere('nisn', 'ILIKE', '%' . $this->search . '%')
                ->orWhere('email', 'ILIKE', '%' . $this->search . '%');
            })->when($this->role_id, function($ptk) {
                $ptk->whereRoleIs($this->role_id, session('semester_id'));
            })->paginate($this->per_page),
            'hak_akses' => Role::whereNotIn('id', [1,2,6])->get(),
            'breadcrumbs' => [
                ['link' => "/", 'name' => "Beranda"], ['link' => '#', 'name' => 'Pengaturan'], ['name' => "Hak Akses Pengguna"]
            ],
            'tombol_add' => 
            [
                'wire' => 'generatePengguna',
                'link' => '',
                'color' => 'success',
                'text' => 'Atur Ulang Pengguna'
            ]
        ]);
    }
    private function check_email($user, $field){
        $loggedUser = auth()->user();
        $random = Str::random(8);
		$user->email = ($user->email != $loggedUser->email) ? $user->email : strtolower($random).'@erapor-smk.net';
		$user->email = strtolower($user->email);
        if($field == 'guru_id'){
            $find_user_email = User::where('email', $user->email)->where($field, '<>', $user->ptk_id)->first();
		} else {
            $find_user_email = User::where('email', $user->email)->where($field, '<>', $user->peserta_didik_id)->first();
		}
		if($find_user_email){
			$user->email = strtolower($random).'@erapor-smk.net';
		}
        return $user->email;
    }
    public function generatePtk(){
        $data = Guru::where('sekolah_id', session('sekolah_id'))->whereNotNull('email')->get();
        $jenis_tu = Helper::jenis_gtk('tendik');
		$asesor = Helper::jenis_gtk('asesor');
        if($data){
            foreach($data as $d){
                $new_password = strtolower(Str::random(8));
                $user = User::where('guru_id', $d->guru_id)->first();
                if(!$user){
                    $user_email = $this->check_email($d, 'guru_id');
                    $user = User::create([
                        'name' => $d->nama,
						'email' => $user_email,
						'nuptk'	=> $d->nuptk,
						'password' => bcrypt($new_password),
						'last_sync'	=> now(),
						'sekolah_id'	=> session('sekolah_id'),
						'password_dapo'	=> md5($new_password),
						'guru_id'	=> $d->guru_id,
						'default_password' => $new_password,
                    ]);
                }
                if($jenis_tu->contains($d->jenis_ptk_id)){
                    $role = Role::where('name', 'tu')->first();
                } elseif($asesor->contains($d->jenis_ptk_id)){
                    $role = Role::where('name', 'user')->first();
                } else {
                    $role = Role::where('name', 'guru')->first();
                }
                if(!$user->hasRole($role, session('semester_id'))){
                    $user->attachRole($role, session('semester_id'));
                }
                $find_rombel = Rombongan_belajar::where('guru_id', $d->guru_id)->where('semester_id', session('semester_aktif'))->where('jenis_rombel', 1)->first();
				if($find_rombel){
                    $WalasRole = Role::where('name', 'wali')->first();
                    if(!$user->hasRole($WalasRole, session('semester_id'))){
                        $user->attachRole($WalasRole, session('semester_id'));
                    }
                }
                $find_mapel_p5 = Pembelajaran::where('guru_id', $d->guru_id)->where('semester_id', session('semester_aktif'))->has('tema')->first();
                if($find_mapel_p5){
                    $p5Role = Role::where('name', 'guru-p5')->first();
                    if(!$user->hasRole($p5Role, session('semester_id'))){
                        $user->attachRole($p5Role, session('semester_id'));
                    }
                }
                $find_ekskul = Ekstrakurikuler::where('guru_id', $d->guru_id)->where('semester_id', session('semester_aktif'))->first();
                if($find_ekskul){
                    $PembinaRole = Role::where('name', 'pembina_ekskul')->first();
                    if(!$user->hasRole($PembinaRole, session('semester_id'))){
                        $user->attachRole($PembinaRole, session('semester_id'));
                    }
                }
            }
        }
        $this->alert('success', 'Berhasil', [
            'text' => 'Pengguna PTK berhasil diperbaharui',
            'showCancelButton' => true,
            'cancelButtonText' => 'OK',
            'timer' => null
        ]);
    }
    public function generatePd(){
        $data = Peserta_didik::where('sekolah_id', session('sekolah_id'))->get();
        if($data){
            foreach($data as $d){
                $new_password = strtolower(Str::random(8));
                $user = User::where('peserta_didik_id', $d->peserta_didik_id)->first();
                if(!$user){
                    $user_email = $this->check_email($d, 'peserta_didik_id');
                    $user = User::create([
                        'name' => $d->nama,
						'email' => $user_email,
						'nisn'	=> $d->nisn,
						'password' => bcrypt($new_password),
						'last_sync'	=> now(),
						'sekolah_id'	=> session('sekolah_id'),
						'password_dapo'	=> md5($new_password),
						'peserta_didik_id'	=> $d->peserta_didik_id,
						'default_password' => $new_password,
                    ]);
                }
                $role = Role::where('name', 'siswa')->first();
                if(!$user->hasRole($role, session('semester_id'))){
                    $user->attachRole($role, session('semester_id'));
                }
            }
        }
        $this->alert('success', 'Berhasil', [
            'text' => 'Pengguna PD berhasil diperbaharui',
            'showCancelButton' => true,
            'cancelButtonText' => 'OK',
            'timer' => null
        ]);
    }
    public function generatePengguna(){
        $this->alert('question', 'Apakah Anda yakin?', [
            'text' => 'Tindakan ini tidak dapat dikembalikan!',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Akun PTK',
            'showLoaderOnConfirm' => true,
            'onConfirmed' => 'generatePtk',
            'showDenyButton' => true,
            'denyButtonText' => 'Akun PD',
            'showLoaderOnDeny' => true,
            'showCancelButton' => true,
            'cancelButtonText' => 'Batal',
            'onDenied' => 'generatePd',
            'onDismissed' => 'cancelled',
            'allowOutsideClick' => false,//'() => !Swal.isLoading()',
            'timer' => null
        ]);
    }
    public function view($user_id){
        $this->reset(['akses']);
        $this->pengguna = User::find($user_id);
        $this->roles = Role::find([7,8,9]);
        $this->emit('openView');
    }
    public function hapusAkses($user_id, $role){
        $this->pengguna->detachRole($role, session('semester_id'));
        $this->alert('success', 'Berhasil', [
            'text' => 'Hak Akses berhasil dihapus'
        ]);
        $this->pengguna = User::find($user_id);
    }
    public function destroy($user_id){
        $this->alert('question', 'Apakah Anda yakin?', [
            'text' => 'Tindakan ini tidak dapat dikembalikan!',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Yakin',
            'onConfirmed' => 'confirmed',
            'showCancelButton' => true,
            'cancelButtonText' => 'Batal',
            'allowOutsideClick' => false,//'() => !Swal.isLoading()',
            'timer' => null
        ]);
    }
    public function update(){
        foreach($this->akses as $akses){
            if(!$this->pengguna->hasRole($akses, session('semester_id'))){
                $this->pengguna->attachRole($akses, session('semester_id'));
            }
        }
        $this->emit('close-modal');
        $this->alert('success', 'Berhasil', [
            'text' => 'Data Pengguna berhasil diperbaharui'
        ]);
        $this->resetPage();
    }
    public function confirmed(){
        $this->alert('success', 'Berhasil', [
            'text' => 'Pengguna berhasil dihapus'
        ]);
        $this->resetPage();
    }
    public function resetPassword($user_id){
        $this->user_id = $user_id;
        $this->alert('question', 'Apakah Anda yakin?', [
            'text' => 'Tindakan ini tidak dapat dikembalikan!',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Yakin',
            'onConfirmed' => 'confirmReset',
            'showCancelButton' => true,
            'cancelButtonText' => 'Batal',
            'allowOutsideClick' => false,//'() => !Swal.isLoading()',
            'timer' => null
        ]);
    }
    public function confirmReset(){
        $user = User::find($this->user_id);
        $user->password = bcrypt($user->default_password);
        if($user->save()){
            $this->alert('success', 'Berhasil', [
                'text' => 'Password Pengguna berhasil direset'
            ]);
            $this->emit('close-modal');
            $this->resetPage();
        } else {
            $this->alert('error', 'Gagal', [
                'text' => 'Password Pengguna gagal direset. Silahkan coba beberapa saat lagi!'
            ]);
        }
    }
}
