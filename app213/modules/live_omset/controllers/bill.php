<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class bill extends CI_Controller
{
    private $controller;
    private $view;
    private $view_form;

    function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            echo "<script>window.location.replace('" . base_url() . "');</script>";
            exit;
        }

        $module = 'transaksi';
        $this->load->library('module_auth', array(
            'module' => $module
        ));

        $this->load->model(array(
            'apps_model', 'bill_model'
        ));

        $this->controller = $this->router->fetch_class();
        $this->view      = 'v'.$this->controller;
        $this->view_form = 'v'.$this->controller.'_form';
    }

    public function index()
    {
        if (!$this->module_auth->read) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_read);
            redirect();
        }

        $data['current'] = 'transaksi';
        $data['apps']    = $this->apps_model->get_active_only();

        $this->load->view($this->view, $data);
    }

    function grid()
    {
		$i=0;
        $responce = new stdClass;
		if($query = $this->bill_model->grid()) {
			foreach($query as $row) {
				$responce->aaData[$i][]=$row->id;
				$responce->aaData[$i][]=date('d-m-Y h:m:s', strtotime($row->tgl));
				$responce->aaData[$i][]=$row->agent_id;
				$responce->aaData[$i][]=$row->op_nama;
				$responce->aaData[$i][]=$row->wp_nama;
				$responce->aaData[$i][]=number_format($row->nominal,0,',','.');
				$responce->aaData[$i][]=$row->ket_pengirim;
				$i++;
			}
		} else {
			$responce->sEcho=1;
			$responce->iTotalRecords="0";
			$responce->iTotalDisplayRecords="0";
			$responce->aaData=array();
		}
		echo json_encode($responce);
    }

    private function fvalidation()
    {
        $this->form_validation->set_rules('tgl','Tanggal','required');
        $this->form_validation->set_rules('op_id','Objek Pajak','required|numeric');
        $this->form_validation->set_rules('nominal','Nominal','numeric');
        // $this->form_validation->set_rules('jalur_id','jalur_id','required|numeric');
        // $this->form_validation->set_rules('agent_id','agent_id','required|trim');
        // $this->form_validation->set_rules('ket_pengirim','ket_pengirim','required|trim');
        $this->form_validation->set_rules('tgl_kirim','Tgl. Kirim','required');
        $this->form_validation->set_rules('tgl_kwitansi','Tgl. Kwitansi','required');
        // $this->form_validation->set_rules('no_kwitansi','no_kwitansi','required|trim');
    }

    private function fpost()
    {
        $data['id'] = $this->input->post('id');
        $data['tgl'] = $this->input->post('tgl');
        $data['op_id'] = $this->input->post('op_id');
        $data['nominal'] = $this->input->post('nominal');
        $data['jalur_id'] = $this->input->post('jalur_id');
        $data['agent_id'] = $this->input->post('agent_id');
        $data['ket_pengirim'] = $this->input->post('ket_pengirim');
        $data['tgl_kirim'] = $this->input->post('tgl_kirim');
        $data['tgl_kwitansi'] = $this->input->post('tgl_kwitansi');
        $data['no_kwitansi'] = $this->input->post('no_kwitansi');

        $data['op_nama'] = $this->input->post('op_nama');
        $data['wp_nama'] = $this->input->post('wp_nama');
        $data['npwp'] = $this->input->post('npwp');

        return $data;
    }

    public function add()
    {
        if (!$this->module_auth->create) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_create);
            redirect(active_module_url($this->controller));
        }

        $post_data  = $this->fpost();

        $data['current'] = 'transaksi';
        $data['apps']    = $this->apps_model->get_active_only();
        $data['faction'] = active_module_url($this->controller.'/add');

        $this->fvalidation();
        if ($this->form_validation->run() == TRUE) {
            $input_post = $post_data;
            $update_data = array(
                'tgl' => empty($input_post['tgl']) ? NULL : date('Y-m-d', strtotime($input_post['tgl'])),
                'op_id' => empty($input_post['op_id']) ? NULL : $input_post['op_id'],
                'nominal' => empty($input_post['nominal']) ? NULL : $input_post['nominal'],
                'jalur_id' => empty($input_post['jalur_id']) ? NULL : $input_post['jalur_id'],
                'agent_id' => empty($input_post['agent_id']) ? NULL : $input_post['agent_id'],
                'ket_pengirim' => empty($input_post['ket_pengirim']) ? NULL : $input_post['ket_pengirim'],
                'tgl_kirim' => empty($input_post['tgl_kirim']) ? NULL : date('Y-m-d', strtotime($input_post['tgl_kirim'])),
                'tgl_kwitansi' => empty($input_post['tgl_kwitansi']) ? NULL : date('Y-m-d', strtotime($input_post['tgl_kwitansi'])),
                'no_kwitansi' => empty($input_post['no_kwitansi']) ? NULL : $input_post['no_kwitansi'],
            );

			if($new_id = $this->bill_model->save($update_data)) {
				$this->session->set_flashdata('msg_success', 'Data telah disimpan, silahkan lengkapi data Bill Item.');
				//redirect(active_module_url($this->controller));
				redirect(active_module_url($this->controller.'/edit/'.$new_id));
			}
        }

        $data['dt']      = $post_data;
        $this->load->view($this->view_form, $data);
    }

    public function edit()
    {
        if (!$this->module_auth->update) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_update);
            redirect(active_module_url($this->controller));
        }

        $p_id = $this->uri->segment(4);

        $data['current'] = 'transaksi';
        $data['apps']    = $this->apps_model->get_active_only();
        $data['faction'] = active_module_url($this->controller."/update/{$p_id}");

        if ($p_id && $get = $this->bill_model->get($p_id)) {
            $data['dt']['id'] = empty($get->id) ? NULL : $get->id;
            $data['dt']['tgl'] = empty($get->tgl) ? NULL : date('d-m-Y', strtotime($get->tgl));
            $data['dt']['op_id'] = empty($get->op_id) ? NULL : $get->op_id;
            $data['dt']['nominal'] = empty($get->nominal) ? NULL : $get->nominal;
            $data['dt']['jalur_id'] = empty($get->jalur_id) ? NULL : $get->jalur_id;
            $data['dt']['agent_id'] = empty($get->agent_id) ? NULL : $get->agent_id;
            $data['dt']['ket_pengirim'] = empty($get->ket_pengirim) ? NULL : $get->ket_pengirim;
            $data['dt']['tgl_kirim'] = empty($get->tgl_kirim) ? NULL : date('d-m-Y', strtotime($get->tgl_kirim));
            $data['dt']['tgl_kwitansi'] = empty($get->tgl_kwitansi) ? NULL : date('d-m-Y', strtotime($get->tgl_kwitansi));
            $data['dt']['no_kwitansi'] = empty($get->no_kwitansi) ? NULL : $get->no_kwitansi;

            //--
            $this->load->model('op_model');
            $op_data = $this->op_model->get($get->op_id);
            $data['dt']['op_nama'] = empty($op_data->nama) ? NULL : $op_data->nama;

            $wp_data = $this->op_model->get_wp($get->op_id);
            $data['dt']['wp_nama'] = empty($wp_data->nama) ? NULL : $wp_data->nama;
            $data['dt']['npwp'] = empty($wp_data->npwp) ? NULL : $wp_data->npwp;

			$this->load->view($this->view_form, $data);
        } else {
            show_404();
        }
    }

    public function update()
    {
        if (!$this->module_auth->update) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_update);
            redirect(active_module_url($this->controller));
        }

        $p_id      = $this->uri->segment(4);
        $post_data = $this->fpost();

        $data['current'] = 'transaksi';
        $data['apps']    = $this->apps_model->get_active_only();
        $data['faction'] = active_module_url($this->controller."/update/{$p_id}");

        $this->fvalidation();
        if ($this->form_validation->run() == TRUE) {
            $input_post  = $post_data;
            $update_data = array(
                'tgl' => empty($input_post['tgl']) ? NULL : date('Y-m-d', strtotime($input_post['tgl'])),
                'op_id' => empty($input_post['op_id']) ? NULL : $input_post['op_id'],
                'nominal' => empty($input_post['nominal']) ? NULL : $input_post['nominal'],
                'jalur_id' => empty($input_post['jalur_id']) ? NULL : $input_post['jalur_id'],
                'agent_id' => empty($input_post['agent_id']) ? NULL : $input_post['agent_id'],
                'ket_pengirim' => empty($input_post['ket_pengirim']) ? NULL : $input_post['ket_pengirim'],
                'tgl_kirim' => empty($input_post['tgl_kirim']) ? NULL : date('Y-m-d', strtotime($input_post['tgl_kirim'])),
                'tgl_kwitansi' => empty($input_post['tgl_kwitansi']) ? NULL : date('Y-m-d', strtotime($input_post['tgl_kwitansi'])),
                'no_kwitansi' => empty($input_post['no_kwitansi']) ? NULL : $input_post['no_kwitansi'],
            );

            $this->bill_model->update($p_id, $update_data);

            $this->session->set_flashdata('msg_success', 'Data telah disimpan');
            redirect(active_module_url($this->controller));
        }

        $data['dt'] = $post_data;
		$this->load->view($this->view_form, $data);
    }

    public function delete()
    {
        if (!$this->module_auth->delete) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_delete);
            redirect(active_module_url($this->controller));
        }

        $id = $this->uri->segment(4);
        if ($id && $this->bill_model->get($id)) {
            $this->bill_model->delete($id);
            $this->session->set_flashdata('msg_success', 'Data telah dihapus');
            redirect(active_module_url($this->controller));
        } else {
            show_404();
        }
    }




    /* Item */
	function grid_item() {
        $bill_id = $this->uri->segment(4);

		$i=0;
        $responce = new stdClass;
		if($query = $this->bill_model->grid_item($bill_id)) {
			foreach($query as $row) {
				$responce->aaData[$i][]=$row->id;
				$responce->aaData[$i][]=$row->nama_produk;
				$responce->aaData[$i][]=$row->jumlah;
				$responce->aaData[$i][]=$row->nominal;
				$responce->aaData[$i][]=$row->harga_satuan;
				$responce->aaData[$i][]=$row->op_item_nama;
				$i++;
			}
		} else {
			$responce->sEcho=1;
			$responce->iTotalRecords="0";
			$responce->iTotalDisplayRecords="0";
			$responce->aaData=array();
		}
		echo json_encode($responce);
	}

    function new_item() {
        // cuma ambil op item
        $op_id = $this->uri->segment(4);
        
        $this->load->model('op_model');
        $select_data = $this->op_model->get_item($op_id);
        $op_item = '<option value=0>-</option>';
        foreach ($select_data as $row) {
			$op_item .= "<option value={$row->id}>{$row->nama}</option>";
        }
		$item_data->op_item = $op_item;

        echo json_encode($item_data);
    }

    function get_item() {        
        $item_id   = $this->uri->segment(4);
        $op_id     = $this->uri->segment(5);
        $item_data = $this->load->model('bill_item_model')->get($item_id);

        $this->load->model('op_model');
        $select_data = $this->op_model->get_item($op_id);
        $op_item = '<option value=0>-</option>';
        foreach ($select_data as $row) {
			if ($row->id == $item_data->op_item_id)
				$op_item .= "<option value={$row->id} selected >{$row->nama}</option>";
			else
				$op_item .= "<option value={$row->id}>{$row->nama}</option>";
        }
		$item_data->op_item = $op_item;
        
        echo json_encode($item_data);
    }

	function proces_item() {
        $mode = $this->uri->segment(4);
		$item_id = $this->input->get_post('item_id');
        $op_item_id = $this->input->get_post('item_op_item_id');

		$update_data = array(
            'bill_id' => $this->input->get_post('bill_id'),
            'nama_produk' => $this->input->get_post('item_nama_produk'),
            'jumlah' => (int) $this->input->get_post('item_jumlah'),
            'nominal' => (float) (int) $this->input->get_post('item_nominal'),
            'harga_satuan' => (float) (int) $this->input->get_post('item_harga_satuan'),
            'op_item_id' => empty($op_item_id) ? NULL : $op_item_id,
		);

		$op_model = $this->load->model('bill_item_model');
		if ($mode == 'add') {
			$op_model->save($update_data);
		} elseif ($mode == 'edit') {
			$op_model->update($item_id, $update_data);
		}
	}

    public function delete_item() {
        if (!$this->module_auth->delete) {
            $this->session->set_flashdata('msg_warning', $this->module_auth->msg_delete);
            redirect(active_module_url($this->controller));
        }

        $id = $this->uri->segment(4);
        $model = $this->load->model('bill_item_model');
        if ($id && $model->get($id)) {
            $model->delete($id);
			echo "sip";
        } else {
            show_404();
        }
    }

    // ---

    public function grid_op() {
		$i=0;
        $responce = new stdClass;
        $this->load->model('op_model');
		if($query = $this->op_model->grid_dlg_bill()) {
			foreach($query as $row) {
				$responce->aaData[$i][]=$row->id;
				$responce->aaData[$i][]=$row->nama;
				$responce->aaData[$i][]=$row->wp_nama;
				$responce->aaData[$i][]=$row->npwp;
				$responce->aaData[$i][]=$row->kelurahan;
				$responce->aaData[$i][]=$row->kecamatan;

				$responce->aaData[$i][]=$row->agent_id;
				$responce->aaData[$i][]=$row->jalur;
				$i++;
			}
		} else {
			$responce->sEcho=1;
			$responce->iTotalRecords="0";
			$responce->iTotalDisplayRecords="0";
			$responce->aaData=array();
		}
		echo json_encode($responce);
    }
}