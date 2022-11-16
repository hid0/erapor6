<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Models\Peserta_didik;
use App\Models\Tp_nilai;
use App\Models\Rencana_penilaian;

class TemplateNilaiTp implements FromView, ShouldAutoSize
{
    use Exportable;
    public function query(string $rencana_penilaian_id, string $rombongan_belajar_id)
    {
        $this->rencana_penilaian_id = $rencana_penilaian_id;
        $this->rombongan_belajar_id = $rombongan_belajar_id;
        return $this;
    }
	public function view(): View
    {
        $rencana_penilaian = Rencana_penilaian::with(['pembelajaran'])->find($this->rencana_penilaian_id);
        $get_mapel_agama = filter_agama_siswa($rencana_penilaian->pembelajaran_id, $this->rombongan_belajar_id);
        $tp_nilai = Tp_nilai::where('rencana_penilaian_id', $this->rencana_penilaian_id)->select('tp_nilai_id', 'rencana_penilaian_id', 'tp_id')->get();
        $data_siswa = Peserta_didik::select('peserta_didik_id', 'nama', 'nisn')->where(function($query) use ($get_mapel_agama){
            $query->whereHas('anggota_rombel', function($query){
                $query->where('rombongan_belajar_id', $this->rombongan_belajar_id);
            });
            if($get_mapel_agama){
                $query->where('agama_id', $get_mapel_agama);
            }
        })->with(['anggota_rombel' => function($query){
            $query->where('rombongan_belajar_id', $this->rombongan_belajar_id);
            $query->with(['nilai_tp' => function($query){
                $query->whereHas('tp_nilai', function($query){
                    $query->whereHas('rencana_penilaian', function($query){
                        $query->where('rencana_penilaian_id', $this->rencana_penilaian_id);
                    });
                });
            }]);
        }])->orderBy('nama')->get();
        $params = array(
            'tp_nilai' => $tp_nilai,
			'data_siswa' => $data_siswa,
		);
        return view('content.unduhan.template_nilai_tp', $params);
    }
}
