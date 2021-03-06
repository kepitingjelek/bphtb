<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class approval extends CI_Controller
{
    private $module = 'penelitian';
    private $controller = 'approval';
    private $current = 'penelitian';
    private $levelv;

    function __construct()
    {
        parent::__construct();
        if (!$this->session->userdata('login')) {
            $this->session->set_flashdata('msg_warning', 'Session telah kadaluarsa, silahkan login ulang.');
            redirect('login');
            exit;
        }

        $this->load->library('module_auth', array(
            'module' => $this->module
        ));

        $this->load->model(array(
            'apps_model',
            'bphtb_model',
            'sspd_model',
            'ppat_model',
            'dasar_model',
            'perolehan_model',
            'ppat_user_model',
            'tp_model',
            'approval_model',
            'penerimaan_model',
            'validasi_model',
            'validasi_user_model',
            'status_hak_model',
        ));

        //cek level user/pejabat yg memvalidasi
        //mungkin user tanpa level disetarakan dengan level=1 (staff) saja
        $this->levelv = $this->validasi_user_model->get_level();

        $this->load->helper(active_module());
    }

    public function index()
    {
        if (!$this->module_auth->read) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_read);
            redirect(active_module_url(''));
        }

        $data['current'] = $this->current;
        $data['apps']    = $this->apps_model->get_active_only();

        $options = array(
            '1' => 'BELUM PROSES',
            '2' => 'SUDAH PROSES',
        );
        $js = 'id="proses_id" class="input-medium"';
        $select = form_dropdown('proses_id', $options, 1, $js);
        $select = preg_replace("/[\r\n]+/", "", $select);
        $data['select_proses'] = $select;

        $this->load->view('vapproval', $data);
    }

    function grid()
    {
        // $tgl1 = $this->uri->segment(4);
        // $tgl2 = $this->uri->segment(5);
        // $proses_id = $this->uri->segment(6);
        
        // pake method otable.push
        $tgl1 = $this->input->get_post('tgl1', TRUE);
        $tgl2 = $this->input->get_post('tgl2', TRUE);
        $proses_id = $this->input->get_post('proses_id', TRUE);

        if($tgl1=='') {
            $tgl1 = date('Y-01-01');
            $tgl2 = date('Y-m-d');;
        } else {
            $tgl1 = date('Y-m-d', strtotime($tgl1));
            $tgl2 = date('Y-m-d', strtotime($tgl2));
        }
        $proses_id = ($proses_id) ? $proses_id : 1;


/*
-- // BASE QUERY
with user_level as (
    select vl.id, vl.level, vl.min_value, vl.max_value
    from bphtb_validasi_user vu
    inner join bphtb_validasi_level vl on vl.id=vu.level_id
    where vu.user_id=98 --83 -- 98
),
approval as (
  select distinct on (a.validasi_id) validasi_id, a.id id, a.tgl_approval,
    a.user_id, a.level_id, a.level, af.id approval_final_id
  from bphtb_approval a
  left join bphtb_approval_final af on af.approval_id = a.id
  order by a.validasi_id, a.id desc
)

select
--v.id, v.sspd_id, v.terhutang, a.level, v.terhutang*v.bagian/greatest(v.pembagi,1) as tt, a.id, a.approval_final_id
coalesce(a.approval_final_id, v.id) as id, '{rownum}' as rownum,
get_approvalno(a.id) as approvalno,
(case when get_approvalno(a.id) is null then null else a.tgl_approval end) as tgl_approval,
get_nop_sspd(v.sspd_id,true) as nop, v.thn_pajak_sppt, v.wp_nama,
coalesce(v.bphtb_sudah_dibayarkan,0) as bayar, get_sspdno(v.sspd_id) as sspdno,
ss.tgl_transaksi, ppat.nama as ppatnm

from bphtb_validasi v
inner join bphtb_sspd ss on ss.id=v.sspd_id
inner join bphtb_ppat ppat on ppat.id=v.ppat_id
left join approval a on a.validasi_id = v.id
left join user_level ul on 1=1

--belum proses
where (v.terhutang*v.bagian/greatest(v.pembagi,1))-coalesce(v.pengurang,0) <= v.bphtb_sudah_dibayarkan
and (v.terhutang*v.bagian/greatest(v.pembagi,1))-coalesce(v.pengurang,0) >= ul.min_value
and (a.id is null or a.level = (ul.level-1))

--sudah proses
where a.approval_final_id is not null and a.level = ul.level
*/

        $this->load->library('Datatables');
        $this->datatables->select("coalesce(a.approval_final_id, v.id) as id, {rownum} as rownum,
            get_approvalno(a.id) as approvalno,
            (case when get_approvalno(a.id) is null then null else a.tgl_approval end) as tgl_approval,
            get_nop_sspd(v.sspd_id,true) as nop, v.thn_pajak_sppt, v.wp_nama,
            coalesce(v.bphtb_sudah_dibayarkan,0) as bayar, get_sspdno(v.sspd_id) as sspdno,
            ss.tgl_transaksi, ppat.nama as ppatnm", false);
        $this->datatables->from('bphtb_validasi v');
        $this->datatables->join('bphtb_sspd ss', 'ss.id=v.sspd_id');
        $this->datatables->join('bphtb_ppat ppat', 'ppat.id=v.ppat_id');

        $this->datatables->join('(
                select distinct on (a.validasi_id) validasi_id, a.id id, a.tgl_approval,
                    a.user_id, a.level_id, a.level, af.id approval_final_id
                from bphtb_approval a
                left join bphtb_approval_final af on af.approval_id = a.id
                order by a.validasi_id, a.id desc
            ) a', 'a.validasi_id=v.id', 'left');

        $this->datatables->join('(
                select vl.id, vu.user_id, vl.level, vl.min_value, vl.max_value, 1 as ngakalin
                from bphtb_validasi_user vu
                inner join bphtb_validasi_level vl on vl.id=vu.level_id
                where vu.user_id='.$this->levelv->user_id.'
            ) ul', 'ul.ngakalin=ul.ngakalin');

        //hanya tampil ketika user punya hak akses u/ mengapprove
        // if($this->session->userdata('userid') != $this->levelv->user_id)
            // $this->datatables->where("v.id < 0");

        if($proses_id == 1) {
            if($this->input->get('sSearch') == '')
                $this->datatables->filter("date(ss.tgl_transaksi) between '{$tgl1}' and '{$tgl2}'");

            //baru niyy 2015-06-08
            // $this->datatables->where("(v.terhutang-v.pengurang) <= v.bphtb_sudah_dibayarkan");
            // $this->datatables->where("((v.terhutang*v.bagian/v.pembagi)-v.pengurang) <= v.bphtb_sudah_dibayarkan");

            $this->datatables->where('(
                    (v.terhutang*v.bagian/greatest(v.pembagi,1))-coalesce(v.pengurang,0) <= v.bphtb_sudah_dibayarkan
                    and (v.terhutang*v.bagian/greatest(v.pembagi,1))-coalesce(v.pengurang,0) >= ul.min_value
                    and (a.id is null or a.level = ('.$this->levelv->level.'-1))
                )');

        } else {
            if($this->input->get('sSearch') == '')
                // $this->datatables->filter("date(atgl_approval) between '{$tgl1}' and '{$tgl2}'");
                $this->datatables->filter("date(ss.tgl_transaksi) between '{$tgl1}' and '{$tgl2}'");

                $this->datatables->where('a.approval_final_id is not null');
                $this->datatables->where('a.level = ul.level');
        }

        $this->datatables->date_column('3,9');
        $this->datatables->rupiah_column('7');
        echo $this->datatables->generate();
    }

    //admin
    private function fvalidation()
    {
        $this->form_validation->set_error_delimiters('<span>', '</span>');

        $this->form_validation->set_rules('tgl_approval','Tgl.Approval','required|callback_valid_date');
        $this->form_validation->set_rules('nop_thn','NOP-Thn.Pajak SPPT','trim|required');
    }

    private function fpost()
    {
        $data['id'] = $this->input->post('id');
        $data['tahun'] = $this->input->post('tahun');
        $data['kode'] = $this->input->post('kode');
        $data['no_sspd'] = $this->input->post('no_sspd');
        $data['ppat_id'] = $this->input->post('ppat_id');
        $data['wp_nama'] = $this->input->post('wp_nama');
        $data['wp_npwp'] = $this->input->post('wp_npwp');
        $data['wp_alamat'] = $this->input->post('wp_alamat');
        $data['wp_blok_kav'] = $this->input->post('wp_blok_kav');
        $data['wp_kelurahan'] = $this->input->post('wp_kelurahan');
        $data['wp_rt'] = $this->input->post('wp_rt');
        $data['wp_rw'] = $this->input->post('wp_rw');
        $data['wp_kecamatan'] = $this->input->post('wp_kecamatan');
        $data['wp_kota'] = $this->input->post('wp_kota');
        $data['wp_provinsi'] = $this->input->post('wp_provinsi');
        $data['wp_identitas'] = $this->input->post('wp_identitas');
        $data['wp_identitaskd'] = $this->input->post('wp_identitaskd');
        $data['wp_kdpos'] = $this->input->post('wp_kdpos');
        $data['tgl_transaksi'] = $this->input->post('tgl_transaksi');
        $data['kd_propinsi'] = $this->input->post('kd_propinsi');
        $data['kd_dati2'] = $this->input->post('kd_dati2');
        $data['kd_kecamatan'] = $this->input->post('kd_kecamatan');
        $data['kd_kelurahan'] = $this->input->post('kd_kelurahan');
        $data['kd_blok'] = $this->input->post('kd_blok');
        $data['no_urut'] = $this->input->post('no_urut');
        $data['kd_jns_op'] = $this->input->post('kd_jns_op');
        $data['thn_pajak_sppt'] = $this->input->post('thn_pajak_sppt');
        $data['op_alamat'] = $this->input->post('op_alamat');
        $data['op_blok_kav'] = $this->input->post('op_blok_kav');
        $data['op_rt'] = $this->input->post('op_rt');
        $data['op_rw'] = $this->input->post('op_rw');
        $data['no_sertifikat'] = $this->input->post('no_sertifikat');
        $data['perolehan_id'] = $this->input->post('perolehan_id');

        $data['bumi_luas'] = to_decimal($this->input->post('bumi_luas'));
        $data['bumi_njop'] = to_decimal($this->input->post('bumi_njop'));
        $data['bng_luas'] = to_decimal($this->input->post('bng_luas'));
        $data['bng_njop'] = to_decimal($this->input->post('bng_njop'));
        $data['njop'] = to_decimal($this->input->post('njop'));
        $data['npop'] = to_decimal($this->input->post('npop'));
        $data['npoptkp'] = to_decimal($this->input->post('npoptkp'));
        $data['tarif'] = to_decimal($this->input->post('tarif'));
        $data['terhutang'] = to_decimal($this->input->post('terhutang'));
        $data['bagian'] = to_decimal($this->input->post('bagian'));
        $data['pembagi'] = to_decimal($this->input->post('pembagi'));
        $data['tarif_pengurang'] = to_decimal($this->input->post('tarif_pengurang'));
        $data['pengurang'] = to_decimal($this->input->post('pengurang'));
        $data['bphtb_sudah_dibayarkan'] = to_decimal($this->input->post('bphtb_sudah_dibayarkan'));
        $data['denda'] = to_decimal($this->input->post('denda'));
        $data['restitusi'] = to_decimal($this->input->post('restitusi'));
        $data['bphtb_harus_dibayarkan'] = to_decimal($this->input->post('bphtb_harus_dibayarkan'));
        $data['persen_pengurang_sendiri'] = to_decimal($this->input->post('persen_pengurang_sendiri'));
        $data['jml_pph'] = to_decimal($this->input->post('jml_pph'));

        $data['status_pembayaran'] = $this->input->post('status_pembayaran');
        $data['dasar_id'] = $this->input->post('dasar_id');
        $data['header_id'] = $this->input->post('header_id');
        $data['tgl_print'] = $this->input->post('tgl_print');
        $data['tgl_approval'] = $this->input->post('tgl_approval');
        $data['keterangan'] = $this->input->post('keterangan');
        $data['status_daftar'] = $this->input->post('status_daftar');
        $data['pp_nomor_pengurang_sendiri'] = $this->input->post('pp_nomor_pengurang_sendiri');
        $data['no_ajb'] = $this->input->post('no_ajb');
        $data['tgl_ajb'] = $this->input->post('tgl_ajb');

        $data['tgl_pph'] = $this->input->post('tgl_pph');
        $data['posted'] = $this->input->post('posted');
        $data['pos_tp_id'] = $this->input->post('pos_tp_id');
        $data['status_validasi'] = $this->input->post('status_validasi');
        $data['status_bpn'] = $this->input->post('status_bpn');
        $data['tgl_jatuh_tempo'] = $this->input->post('tgl_jatuh_tempo');
        $data['hasil_penelitian'] = $this->input->post('hasil_penelitian');

        $data['file1'] = $this->input->post('file1');
        $data['file2'] = $this->input->post('file2');
        $data['file3'] = $this->input->post('file3');
        $data['file4'] = $this->input->post('file4');
        $data['file5'] = $this->input->post('file5');
        $data['file6'] = $this->input->post('file6');
        $data['file7'] = $this->input->post('file7');
        $data['file8'] = $this->input->post('file8');
        $data['file9'] = $this->input->post('file9');
        $data['file10'] = $this->input->post('file10');

        $data['create_uid'] = $this->input->post('create_uid');
        $data['update_uid'] = $this->input->post('update_uid');
        $data['created'] = $this->input->post('created');
        $data['updated'] = $this->input->post('updated');

        $data['no_sk'] = $this->input->post('no_sk');
        $data['ketetapan_no'] = $this->input->post('ketetapan_no');
        $data['ketetapan_tgl'] = $this->input->post('ketetapan_tgl');
        $data['ketetapan_atas_sspd_no'] = $this->input->post('ketetapan_atas_sspd_no');
        $data['ketetapan_jatuh_tempo_tgl'] = $this->input->post('ketetapan_jatuh_tempo_tgl');
        $data['pengurangan_sk'] = $this->input->post('pengurangan_sk');
        $data['pengurangan_sk_tgl'] = $this->input->post('pengurangan_sk_tgl');
        $data['pengurangan_jatuh_tempo_tgl'] = $this->input->post('pengurangan_jatuh_tempo_tgl');

        $data['sspdno'] = $this->input->post('sspdno');
        $data['nop_thn'] = $this->input->post('nop_thn');

        $data['approvalno'] = $this->input->post('approvalno');
        $data['tgl_approval'] = $this->input->post('tgl_approval');

        $data['harga_transaksi'] = to_decimal($this->input->post('harga_transaksi'));
        $data['npopkp'] = to_decimal($this->input->post('npopkp'));

        return $data;
    }

    public function approve()
    {
        if (!$this->module_auth->update) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_update);
            redirect(active_module_url($this->controller));
        }

        $data['current'] = $this->current;
        $data['apps']    = $this->apps_model->get_active_only();
        $data['faction'] = active_module_url($this->controller . '/do_approve');

        $data['ppat']      = $this->ppat_model->get_all();
        $data['dasar']     = $this->dasar_model->get_all();
        $data['perolehan'] = $this->perolehan_model->get_all();
        $data['tp'] = $this->tp_model->get_all();
        $data['status_hak'] = $this->status_hak_model->get_all();

        $data['mode']   = 'edit';

        $id = (int) $this->uri->segment(4);
        if ($id && $get = $this->validasi_model->get($id)) {
            $nopthn = $this->sspd_model->get_nopthn($get->sspd_id);
            $sspdno = $this->sspd_model->get_sspdno($get->sspd_id);

            $data['dt']['id']      = $get->id;
            $data['dt']['nop_thn'] = $nopthn;
            $data['dt']['sspdno']  = $sspdno;
            ;
            $data['dt']['ppat_id'] = empty($get->ppat_id) ? 0 : $get->ppat_id;
            $data['dt']['wp_nama'] = empty($get->wp_nama) ? NULL : $get->wp_nama;
            $data['dt']['wp_npwp'] = empty($get->wp_npwp) ? NULL : $get->wp_npwp;
            $data['dt']['wp_alamat'] = empty($get->wp_alamat) ? NULL : $get->wp_alamat;
            $data['dt']['wp_blok_kav'] = empty($get->wp_blok_kav) ? NULL : $get->wp_blok_kav;
            $data['dt']['wp_kelurahan'] = empty($get->wp_kelurahan) ? NULL : $get->wp_kelurahan;
            $data['dt']['wp_rt'] = empty($get->wp_rt) ? NULL : $get->wp_rt;
            $data['dt']['wp_rw'] = empty($get->wp_rw) ? NULL : $get->wp_rw;
            $data['dt']['wp_kecamatan'] = empty($get->wp_kecamatan) ? NULL : $get->wp_kecamatan;
            $data['dt']['wp_kota'] = empty($get->wp_kota) ? NULL : $get->wp_kota;
            $data['dt']['wp_provinsi'] = empty($get->wp_provinsi) ? NULL : $get->wp_provinsi;
            $data['dt']['wp_identitas'] = empty($get->wp_identitas) ? NULL : $get->wp_identitas;
            $data['dt']['wp_identitaskd'] = empty($get->wp_identitaskd) ? NULL : $get->wp_identitaskd;
            $data['dt']['tgl_transaksi'] = empty($get->tgl_transaksi) ? NULL : date('d-m-Y', strtotime($get->tgl_transaksi));
            $data['dt']['kd_propinsi'] = empty($get->kd_propinsi) ? NULL : $get->kd_propinsi;
            $data['dt']['kd_dati2'] = empty($get->kd_dati2) ? NULL : $get->kd_dati2;
            $data['dt']['kd_kecamatan'] = empty($get->kd_kecamatan) ? NULL : $get->kd_kecamatan;
            $data['dt']['kd_kelurahan'] = empty($get->kd_kelurahan) ? NULL : $get->kd_kelurahan;
            $data['dt']['kd_blok'] = empty($get->kd_blok) ? NULL : $get->kd_blok;
            $data['dt']['no_urut'] = empty($get->no_urut) ? NULL : $get->no_urut;
            $data['dt']['kd_jns_op'] = empty($get->kd_jns_op) ? NULL : $get->kd_jns_op;
            $data['dt']['thn_pajak_sppt'] = empty($get->thn_pajak_sppt) ? NULL : $get->thn_pajak_sppt;
            $data['dt']['op_alamat'] = empty($get->op_alamat) ? NULL : $get->op_alamat;
            $data['dt']['op_blok_kav'] = empty($get->op_blok_kav) ? NULL : $get->op_blok_kav;
            $data['dt']['op_rt'] = empty($get->op_rt) ? NULL : $get->op_rt;
            $data['dt']['op_rw'] = empty($get->op_rw) ? NULL : $get->op_rw;
            $data['dt']['bumi_luas'] = empty($get->bumi_luas) ? 0 : $get->bumi_luas;
            $data['dt']['bumi_njop'] = empty($get->bumi_njop) ? 0 : $get->bumi_njop;
            $data['dt']['bng_luas'] = empty($get->bng_luas) ? 0 : $get->bng_luas;
            $data['dt']['bng_njop'] = empty($get->bng_njop) ? 0 : $get->bng_njop;
            $data['dt']['no_sertifikat'] = empty($get->no_sertifikat) ? NULL : $get->no_sertifikat;
            $data['dt']['njop'] = empty($get->njop) ? 0 : $get->njop;
            $data['dt']['perolehan_id'] = empty($get->perolehan_id) ? 0 : $get->perolehan_id;
            $data['dt']['npop'] = empty($get->npop) ? 0 : $get->npop;
            $data['dt']['npoptkp'] = empty($get->npoptkp) ? 0 : $get->npoptkp;
            $data['dt']['tarif'] = empty($get->tarif) ? 0 : $get->tarif;
            $data['dt']['terhutang'] = empty($get->terhutang) ? 0 : $get->terhutang;
            $data['dt']['bagian'] = empty($get->bagian) ? 0 : $get->bagian;
            $data['dt']['pembagi'] = empty($get->pembagi) ? 0 : $get->pembagi;
            $data['dt']['tarif_pengurang'] = empty($get->tarif_pengurang) ? 0 : $get->tarif_pengurang;
            $data['dt']['pengurang'] = empty($get->pengurang) ? 0 : $get->pengurang;
            $data['dt']['bphtb_sudah_dibayarkan'] = empty($get->bphtb_sudah_dibayarkan) ? 0 : $get->bphtb_sudah_dibayarkan;
            $data['dt']['denda'] = empty($get->denda) ? 0 : $get->denda;
            $data['dt']['restitusi'] = empty($get->restitusi) ? 0 : $get->restitusi;
            $data['dt']['bphtb_harus_dibayarkan'] = empty($get->bphtb_harus_dibayarkan) ? 0 : $get->bphtb_harus_dibayarkan;
            $data['dt']['status_pembayaran'] = empty($get->status_pembayaran) ? 0 : $get->status_pembayaran;
            $data['dt']['dasar_id'] = empty($get->dasar_id) ? 0 : $get->dasar_id;
            $data['dt']['create_uid'] = empty($get->create_uid) ? NULL : $get->create_uid;
            $data['dt']['update_uid'] = empty($get->update_uid) ? NULL : $get->update_uid;
            $data['dt']['created'] = empty($get->created) ? NULL : date('d-m-Y', strtotime($get->created));
            $data['dt']['updated'] = empty($get->updated) ? NULL : date('d-m-Y', strtotime($get->updated));
            $data['dt']['header_id'] = empty($get->header_id) ? 0 : $get->header_id;
            $data['dt']['tgl_print'] = empty($get->tgl_print) ? NULL : date('d-m-Y', strtotime($get->tgl_print));
            $data['dt']['tgl_approval'] = empty($get->tgl_approval) ? NULL : date('d-m-Y', strtotime($get->tgl_approval));
            $data['dt']['file1'] = empty($get->file1) ? NULL : $get->file1;
            $data['dt']['file2'] = empty($get->file2) ? NULL : $get->file2;
            $data['dt']['file3'] = empty($get->file3) ? NULL : $get->file3;
            $data['dt']['file4'] = empty($get->file4) ? NULL : $get->file4;
            $data['dt']['file5'] = empty($get->file5) ? NULL : $get->file5;
            $data['dt']['wp_kdpos'] = empty($get->wp_kdpos) ? NULL : $get->wp_kdpos;
            $data['dt']['file6'] = empty($get->file6) ? NULL : $get->file6;
            $data['dt']['file7'] = empty($get->file7) ? NULL : $get->file7;
            $data['dt']['file8'] = empty($get->file8) ? NULL : $get->file8;
            $data['dt']['file9'] = empty($get->file9) ? NULL : $get->file9;
            $data['dt']['file10'] = empty($get->file10) ? NULL : $get->file10;
            $data['dt']['keterangan'] = empty($get->keterangan) ? NULL : $get->keterangan;
            $data['dt']['status_daftar'] = empty($get->status_daftar) ? 0 : $get->status_daftar;
            $data['dt']['persen_pengurang_sendiri'] = empty($get->persen_pengurang_sendiri) ? 0 : $get->persen_pengurang_sendiri;
            $data['dt']['pp_nomor_pengurang_sendiri'] = empty($get->pp_nomor_pengurang_sendiri) ? NULL : $get->pp_nomor_pengurang_sendiri;
            $data['dt']['no_ajb'] = empty($get->no_ajb) ? NULL : $get->no_ajb;
            $data['dt']['tgl_ajb'] = empty($get->tgl_ajb) ? NULL : date('d-m-Y', strtotime($get->tgl_ajb));

            $data['dt']['wp_nama_asal'] = empty($get->wp_nama_asal) ? NULL : $get->wp_nama_asal;

            $data['dt']['jml_pph'] = empty($get->jml_pph) ? 0 : $get->jml_pph;
            $data['dt']['tgl_pph'] = empty($get->tgl_pph) ? NULL : date('d-m-Y', strtotime($get->tgl_pph));
            $data['dt']['posted'] = empty($get->posted) ? 0 : $get->posted;
            $data['dt']['pos_tp_id'] = empty($get->pos_tp_id) ? 0 : $get->pos_tp_id;
            $data['dt']['status_validasi'] = empty($get->status_validasi) ? 0 : $get->status_validasi;
            $data['dt']['status_bpn'] = empty($get->status_bpn) ? 0 : $get->status_bpn;
            $data['dt']['tgl_jatuh_tempo'] = empty($get->tgl_jatuh_tempo) ? NULL : date('d-m-Y', strtotime($get->tgl_jatuh_tempo));
            $data['dt']['hasil_penelitian'] = empty($get->hasil_penelitian) ? NULL : $get->hasil_penelitian;
            $data['dt']['no_sk'] = empty($get->no_sk) ? NULL : $get->no_sk;
            $data['dt']['ketetapan_no'] = empty($get->ketetapan_no) ? NULL : $get->ketetapan_no;
            $data['dt']['ketetapan_tgl'] = empty($get->ketetapan_tgl) ? NULL : date('d-m-Y', strtotime($get->ketetapan_tgl));
            $data['dt']['ketetapan_atas_sspd_no'] = empty($get->ketetapan_atas_sspd_no) ? NULL : $get->ketetapan_atas_sspd_no;
            $data['dt']['ketetapan_jatuh_tempo_tgl'] = empty($get->ketetapan_jatuh_tempo_tgl) ? NULL : date('d-m-Y', strtotime($get->ketetapan_jatuh_tempo_tgl));
            $data['dt']['pengurangan_sk'] = empty($get->pengurangan_sk) ? NULL : $get->pengurangan_sk;
            $data['dt']['pengurangan_sk_tgl'] = empty($get->pengurangan_sk_tgl) ? NULL : date('d-m-Y', strtotime($get->pengurangan_sk_tgl));
            $data['dt']['pengurangan_jatuh_tempo_tgl'] = empty($get->pengurangan_jatuh_tempo_tgl) ? NULL : date('d-m-Y', strtotime($get->pengurangan_jatuh_tempo_tgl));
            $data['dt']['verifikasi_uid'] = empty($get->verifikasi_uid) ? NULL : $get->verifikasi_uid;
            $data['dt']['verifikasi_date'] = empty($get->verifikasi_date) ? NULL : date('d-m-Y', strtotime($get->verifikasi_date));
            $data['dt']['pbb_nop'] = empty($get->pbb_nop) ? NULL : $get->pbb_nop;
            $data['dt']['verifikasi_bphtb_uid'] = empty($get->verifikasi_bphtb_uid) ? NULL : $get->verifikasi_bphtb_uid;
            $data['dt']['verifikasi_bphtb_date'] = empty($get->verifikasi_bphtb_date) ? NULL : date('d-m-Y', strtotime($get->verifikasi_bphtb_date));

            $data['dt']['npopkp'] = empty($get->npopkp) ? 0 : $get->npopkp;
            $data['dt']['harga_transaksi'] = empty($get->harga_transaksi) ? 0 : $get->harga_transaksi;

            if(defined('BPHTB_BANJAR') && BPHTB_BANJAR==TRUE) {
                // menyesuaikan ke banjar
                $data['dt']['wp_identitas_asal'] = empty($get->wp_identitas_asal) ? NULL : $get->wp_identitas_asal;
                $data['dt']['wp_npwp_asal'] = empty($get->wp_npwp_asal) ? NULL : $get->wp_npwp_asal;
                $data['dt']['wp_alamat_asal'] = empty($get->wp_alamat_asal) ? NULL : $get->wp_alamat_asal;
                $data['dt']['wp_blok_kav_asal'] = empty($get->wp_blok_kav_asal) ? NULL : $get->wp_blok_kav_asal;
                $data['dt']['wp_kelurahan_asal'] = empty($get->wp_kelurahan_asal) ? NULL : $get->wp_kelurahan_asal;
                $data['dt']['wp_rt_asal'] = empty($get->wp_rt_asal) ? NULL : $get->wp_rt_asal;
                $data['dt']['wp_rw_asal'] = empty($get->wp_rw_asal) ? NULL : $get->wp_rw_asal;
                $data['dt']['wp_kecamatan_asal'] = empty($get->wp_kecamatan_asal) ? NULL : $get->wp_kecamatan_asal;
                $data['dt']['wp_kota_asal'] = empty($get->wp_kota_asal) ? NULL : $get->wp_kota_asal;
                $data['dt']['wp_provinsi_asal'] = empty($get->wp_provinsi_asal) ? NULL : $get->wp_provinsi_asal;
                $data['dt']['wp_identitaskd_asal'] = empty($get->wp_identitaskd_asal) ? NULL : $get->wp_identitaskd_asal;
                $data['dt']['wp_kdpos_asal'] = empty($get->wp_kdpos_asal) ? NULL : $get->wp_kdpos_asal;
                $data['dt']['status_hak_id'] = empty($get->status_hak_id) ? 0 : $get->status_hak_id;

                $this->load->view('vapproval_form_banjar', $data);
            } else
                $this->load->view('vapproval_form', $data);
        } else {
            show_404();
        }
    }

    public function do_approve()
    {
        if (!$this->module_auth->update) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_update);
            redirect(active_module_url($this->controller));
        }
        $data['current'] = $this->current;
        $data['apps']    = $this->apps_model->get_active_only();
        $data['faction'] = active_module_url($this->controller . '/do_approve');

        $data['ppat']      = $this->ppat_model->get_all();
        $data['dasar']     = $this->dasar_model->get_all();
        $data['perolehan'] = $this->perolehan_model->get_all();
        $data['tp'] = $this->tp_model->get_all();
        $data['status_hak'] = $this->status_hak_model->get_all();

        $data['mode'] = 'edit';
        $data['dt']   = $this->fpost();

        $this->fvalidation();
        $id = $data['dt']['id'];
        if ($this->form_validation->run() == TRUE) {
            $input_post = $this->fpost();
            if ($id && $validasi = $this->validasi_model->get($id)) {
                // BPHTB APPROVAL (INGET BUKAN SSPD APPROPAL ! BEDAKAN !)
                $data_approval = array (
                    'validasi_id'  => $validasi->id,
                    'tgl_approval' => date('Y-m-d'),
                    'user_id'      => $this->session->userdata('userid'),
                    'level_id'     => $this->levelv->level_id,
                    'level'        => $this->levelv->level,
                    'created'      => date('Y-m-d'),
                    'create_uid'   => $this->session->userdata('uid'),
                );
                $this->db->insert('bphtb_approval', $data_approval);
                $approval_id = $this->db->insert_id();

                // BPHTB APPROVAL FINAL
                // kalo userid dan npop = user_level dengan range npop yg telah ditetapkan
                $harus_bayar = $input_post['bphtb_harus_dibayarkan'];
                $kode = ($harus_bayar == 0) ? 1 : (($harus_bayar > 0) ? 2 : 3); // 1=Sesuai 2=KB 3=LB

                $bagian    = ($validasi->bagian)>=1 ? $validasi->bagian : 1;
                $pembagi   = ($validasi->pembagi)>=1 ? $validasi->pembagi : 1;
                $pengurang = ($validasi->pengurang)>=0 ? $validasi->pengurang : 0;
                $terhutang = ($validasi->terhutang*$bagian/$pembagi)-$pengurang;
/*
echo "<br>";
echo "terhutang: ".$terhutang; echo "<br>";
echo "min val: ".$this->levelv->min_value; echo "<br>";
echo "max: ".$this->levelv->max_value; echo "<br>";
echo "uid: ".$this->session->userdata('userid'); echo "<br>";
echo "l uid: ".$this->levelv->user_id; echo "<br>";
die();
*/

                // if(($validasi->terhutang >= $this->levelv->min_value && $validasi->terhutang <= $this->levelv->max_value)
                if(($terhutang >= $this->levelv->min_value && $terhutang <= $this->levelv->max_value)
                    && $this->session->userdata('userid') == $this->levelv->user_id) {

                    $sql = "select coalesce(max(no_urut),0)+1 as nourut from bphtb_approval_final
                        where tahun=extract(year from now()) and kode='{$kode}'";
                    $nourut = $this->db->query($sql)->row()->nourut;

                    $data_approval_final = array (
                        'approval_id' => $approval_id,
                        'tahun'       => date('Y'),
                        'kode'        => $kode,
                        'no_urut'     => $nourut,
                        'created'     => date('Y-m-d'),
                        'create_uid'  => $this->session->userdata('uid'),
                    );
                    $this->db->insert('bphtb_approval_final', $data_approval_final);

                    // UPDATE SSPD
                    $sspd_id = $validasi->sspd_id;
                    $data_sspd = array (
                        'status_validasi' => 2, //0 Dartf 1 validated 2 approved
                    );
                    $this->db->where('id', $sspd_id);
                    $this->db->update('bphtb_sspd', $data_sspd);
                }

                /* OLD
                $harus_bayar = $input_post['bphtb_harus_dibayarkan'];
                $kode = ($harus_bayar == 0) ? 1 : (($harus_bayar > 0) ? 2 : 3); // 1=Sesuai 2=KB 3=LB
                $data_validasi = array (
                    'tahun'       => date('Y'),
                    'kode'        => $kode,
                    'no_urut'     => $validasi_id,
                    'validasi_id' => $validasi_id,
                    'created'     => date('Y-m-d'),
                    'create_uid'  => $this->session->userdata('uid'),
                    'level_id'    => $this->levelv->level_id,
                );
                $this->db->insert('bphtb_approval', $data_validasi);
                */

                $this->session->set_flashdata('msg_success', 'Data telah disimpan');
                redirect(active_module_url($this->controller));
            } else {
                $this->session->set_flashdata('msg_error', 'Data tidak ditemukan dalam database!');
                redirect(active_module_url($this->controller));
            }
        }

        if(defined('BPHTB_BANJAR') && BPHTB_BANJAR==TRUE)
            $this->load->view('vapproval_form_banjar', $data);
        else
            $this->load->view('vapproval_form', $data);
    }

    public function batal() {
        if (!$this->module_auth->delete) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_update);
            redirect(active_module_url($this->controller));
        }

        $approval_final_id = (int) $this->uri->segment(4);
        if ($approval_final_id) {
            // hapus BPHTB Approval
            $this->approval_model->batal($approval_final_id);
            echo "ok"; exit;
        }
        echo "not ok";
    }

    // ADDITIONAL FUNC ---------------

    function valid_date($str) {
        if (!empty($str)) {
            if (preg_match("/^(\d{1,2})[- \/.](\d{1,2})[- \/.](\d{4})$/", $str, $values)) {
                // Is a date format (dd mm yyyy)
                if (checkdate($values[2], $values[1], $values[3])) {
                    // Date really exists
                    return TRUE;
                }
            } else {
                $this->form_validation->set_message('valid_date', 'Format %s adalah dd-mm-yyyy contoh:28-02-2014.');
                return FALSE;
            }
        }
        // boleh kosong
        return TRUE;
    }

    function get_npoptkp() {
        $id = $this->uri->segment(4) ? $this->uri->segment(4) : 0;
        if($data = $this->bphtb_model->get_npoptkp($id)) {
            $result =  $data;
        } else {
            $result['npoptkp'] = 0;
            $result['tarif_pengurang'] = 0;
        }
        echo json_encode($result);
    }

    function extract_nopthn($nopthn) {
        $nop_num = preg_replace("/[^0-9]/", "", $nopthn);
        $nop_dot = preg_replace("/([0-9]{2})([0-9]{2})([0-9]{3})([0-9]{3})([0-9]{3})([0-9]{4})([0-9]{1})([0-9]{4})/", "$1.$2.$3.$4.$5.$6.$7.$8", $nop_num);

        $kode = explode(".", $nop_dot);
        list($dt['kd_propinsi'], $dt['kd_dati2'], $dt['kd_kecamatan'], $dt['kd_kelurahan'], $dt['kd_blok'], $dt['no_urut'], $dt['kd_jns_op'], $dt['thn_pajak_sppt']) = $kode;
        return $dt;
    }

    // END FUNC  ----------------------

}
