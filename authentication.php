<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Authentication {

    var $CI;
    //this is the expiration for a non-remember session
    var $session_expire = 3600;

    function __construct() {
        $this->CI = & get_instance();
        $this->CI->load->database();
        $this->CI->load->library('encrypt');

        $admin_session_config = array(
            'sess_cookie_name' => 'admin_session_config',
            'sess_expiration' => 0
        );
        $this->CI->load->library('session', $admin_session_config, 'admin_session');

        $this->CI->load->helper('url');
    }

    function check_access($access, $default_redirect = false, $redirect = false) {
        /*
          we could store this in the session, but by accessing it this way
          if an admin's access level gets changed while they're logged in
          the system will act accordingly.
         */

        $admin = $this->CI->admin_session->userdata('admin');

        $this->CI->db->select('access');
        $this->CI->db->where('id', $admin['id']);
        $this->CI->db->limit(1);
        $result = $this->CI->db->get('admin');
        $result = $result->row();
        $access_values = array();
        $access_values = explode(',', $result->access);
        //result should be an object I was getting odd errors in relation to the object.
        //if $result is an array then the problem is present.
        if (!$result || is_array($result)) {
            $this->logout();
            return false;
        }
        //print_r($access_values);exit;
        if ($access) {
            //echo $access;exit;
            if (in_array("Admin", $access_values)) {
                return true;
            } else if (in_array($access, $access_values)) {
                return true;
            } else {
                if ($redirect) {
                    redirect($redirect);
                } elseif ($default_redirect) {
                    //redirect($this->CI->config->item('admin_folder').'/dashboard/'); // previously written code
                    redirect($this->CI->config->item('admin_folder') . '/login');
                } else {
                    return false;
                }
            }
        }
    }

    /*
      this checks to see if the admin is logged in
      we can provide a link to redirect to, and for the login page, we have $default_redirect,
      this way we can check if they are already logged in, but we won't get stuck in an infinite loop if it returns false.
     */

    function is_logged_in($redirect = false, $default_redirect = true) {

        //var_dump($this->CI->admin_session->userdata('session_id'));
        //$redirect allows us to choose where a customer will get redirected to after they login
        //$default_redirect points is to the login page, if you do not want this, you can set it to false and then redirect wherever you wish.
        $admin = $this->CI->admin_session->userdata('admin');
        //print_r($admin);
        if (!$admin) {
            if ($redirect) {
                $this->CI->admin_session->set_flashdata('redirect', $redirect);
            }

            if ($default_redirect) {
                redirect($this->CI->config->item('admin_folder') . '/login');
            }

            return false;
        } else {

            //check if the session is expired if not reset the timer
            if ($admin['expire'] && $admin['expire'] < time()) {

                $this->logout();
                /* if($redirect)
                  {
                  $this->CI->admin_session->set_flashdata('redirect', $redirect); commented by me
                  } */

                if ($default_redirect) {
                    redirect($this->CI->config->item('admin_folder') . '/login');
                }

                return false;
            } else {

                //update the session expiration to last more time if they are not remembered
                if ($admin['expire']) {
                    $admin['expire'] = time() + $this->session_expire;
                    $this->CI->admin_session->set_userdata(array('admin' => $admin));
                }
            }

            return true;
        }
    }

    /*
      this function does the logging in.
     */

    function login_admin($username, $password, $remember = false) {
        $this->CI->db->select('*');
        //$this->CI->db->where('customer_id', $username);
        $this->CI->db->where('email_id', $username);
        $this->CI->db->where('customer_password', $password);
        $this->CI->db->where('account_activation_status', 1);
///		$this->CI->db->where('status',1);	
        $this->CI->db->where('status_flag !=', 'D');

        //$this->CI->db->where('password',  $password);

        $this->CI->db->limit(1);
        $result = $this->CI->db->get('customer_profile');
        $result = $result->row_array();
        //print_r($result);

        if (sizeof($result) > 0) {
            $admin = array();
            $admin['admin'] = array();
            $admin['admin']['id'] = $result['id'];
            $admin['admin']['customer_name'] = $result['customer_name'];
            $admin['admin']['customer_id'] = $result['customer_id'];
            $admin['admin']['company_name'] = $result['company_name'];
            $admin['admin']['response_allowed'] = $result['survey_response_count'];
            $admin['admin']['no_of_logins'] = $result['no_of_logins'];
            $admin['admin']['logo'] = $result['logo'];
            $admin['admin']['login_flag'] = $result['login_flag'];

            if (!$remember) {
                $admin['admin']['expire'] = time() + $this->session_expire;
            } else {
                $admin['admin']['expire'] = false;
            }

            $this->CI->admin_session->set_userdata($admin);

            return true;
        } else {
            return false;
        }
    }

    //Added VIS
    function login_check($username, $password) {
        $this->CI->db->select('*');
        //$this->CI->db->where('customer_id', $username);
        $this->CI->db->where('email_id', $username);
        $this->CI->db->where('account_activation_status', 0);
        $this->CI->db->where('status_flag !=', 'D');
        $this->CI->db->where('login_count', 0);
        $this->CI->db->limit(1);
        $result = $this->CI->db->get('customer_profile');
        $result = $result->row_array();


        $this->CI->db->select('*');
        $this->CI->db->where('email_id', $username);
        $this->CI->db->limit(1);
        $result1 = $this->CI->db->get('customer_profile');
        $result1 = $result1->row_array();

        $this->CI->db->select('*');
        $this->CI->db->where('email_id', $username);
        //$this->CI->db->where('login_count >=',  1);	
        $this->CI->db->where('(account_activation_status = 0 OR status_flag = "D")', null, false);
        $result3 = $this->CI->db->get('customer_profile');
        $result3 = $result3->row_array();

        /*    	$this->CI->db->select('*');
          $this->CI->db->where('email_id', $username);
          $this->CI->db->where('date_of_expiry <',date('Y-m-d'));

          $result4 = $this->CI->db->get('customer_profile');
          $result4 = $result4->row_array(); */

        if (sizeof($result1) == 0) {

            return 1;
        } else if (sizeof($result) > 0) {

            return 2;
        } else if (sizeof($result3) > 0) {

            return 3;
        }

        return false;
    }

    //Added VIS
    /*
      this function does the logging out
     */
    function logout() {
        //echo "authentication logout";exit;
        $this->CI->admin_session->unset_userdata('admin');
        $this->CI->admin_session->sess_destroy();
    }

    /*
      This function resets the admins password and emails them a copy
     */

    function reset_password($email) {
        $admin = $this->get_admin_by_email($email);
        if ($admin) {
            $this->CI->load->helper('string');
            $this->CI->load->library('email');

            $new_password = random_string('alnum', 8);
            $admin['password'] = sha1($new_password);
            $this->save_admin($admin);

            $this->CI->email->from($this->CI->config->item('email'), $this->CI->config->item('site_name'));
            $this->CI->email->to($email);
            $this->CI->email->subject($this->CI->config->item('site_name') . ': Admin Password Reset');
            $this->CI->email->message('Your password has been reset to ' . $new_password . '.');
            $this->CI->email->send();
            return true;
        } else {
            return false;
        }
    }
    
    function timezone() {
    
    }

    function all_timezone($id) {
        /*
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        include("geoipcity.inc");
        include("geoipregionvars.php");

        $gi = geoip_open("application/libraries/GeoLiteCity.dat", GEOIP_STANDARD);
        $record = geoip_record_by_addr($gi, $remote_ip);


        // print_r($record); exit;

        $this->CI->db->select("zone_name");
        $this->CI->db->where('country_code', $record->country_code);
        $zone = $this->CI->db->get('zone')->row_array();
        date_default_timezone_set($zone['zone_name']);
//echo date("d m Y H:i:s:a"); exit;
         * */
        
                $this->CI->db->select("country_code,time_zone");
                $this->CI->db->where('id', $id);
                $this->CI->db->from('customer_profile');        
                $cp_time_zone = $this->CI->db->get()->result_array();
                
                
                if(!empty($cp_time_zone[0]['time_zone']))
                {
                 //date_default_timezone_set($cp_time_zone['time_zone']);   
                    
                    return $cp_time_zone;
                    
                }
                else
                {
                    $this->CI->db->select("country_code,zone_name as time_zone");
                    $this->CI->db->where('country_code', $cp_time_zone[0]['country_code']);
                    $zone_name = $this->CI->db->get("zone")->result_array();
          
                    return $zone_name;
                }
        
        
    }

    function package_all($id) {

        $this->CI->db->select("login_count,no_of_survey,no_of_question_p_survey,no_of_responses,no_of_devices,no_of_locations,no_of_sub_users,no_of_sms,no_of_email,survey_unlimited,question_unlimited,responses_unlimited,sub_user_unlimited,branching,pdf_report,csv_report,ads,multi_logo_upload,sms_option,email_option,question_type,trial,");
        $this->CI->db->where('customer_profile.id', $id);
        $this->CI->db->from('customer_profile');
        $this->CI->db->join('package', 'package.id = customer_profile.package_id');
        $xxx = $this->CI->db->get()->row_array();



        return $xxx;
    }
    
    function check_active_status($id) {
        
        $this->CI->db->select("account_activation_status");
        $this->CI->db->where('id', $id);
        $this->CI->db->from('customer_profile');        
        $active = $this->CI->db->get()->row_array();
        
        if($active['account_activation_status']==0)
        {
            $this->logout();
        }
    }
    
     function time_zone($id) {
         
         if($id!=""){           
         
         
        
            $this->check_active_status($id);

            $this->CI->db->select("country_code,time_zone");
            $this->CI->db->where('id', $id);
            $this->CI->db->from('customer_profile');        
            $cp_time_zone = $this->CI->db->get()->row_array();


            if(!empty($cp_time_zone['time_zone']))
            {
             //date_default_timezone_set($cp_time_zone['time_zone']);   

                ini_set( 'date.timezone', $cp_time_zone['time_zone'] );

            }
            else
            {
                $this->CI->db->select("zone_name");
                $this->CI->db->where('country_code', $cp_time_zone['country_code']);
                $zone_name = $this->CI->db->get("zone")->row_array();

                ini_set( 'date.timezone', $zone_name['zone_name']);
            }                   
         }
         else
         {
             $this->logout();
         }
    }
    
    
    function time_zone1($id) {
         
         
             
         
         
        
           


                 


                   $this->CI->db->select("country_code,time_zone");
                   $this->CI->db->where('id', $id);
                   $this->CI->db->from('customer_profile');        
                   $cp_time_zone = $this->CI->db->get()->row_array();


                   if(!empty($cp_time_zone['time_zone']))
                   {
                     

                       return $cp_time_zone['time_zone'] ;

                   }
                   else
                   {
                       $this->CI->db->select("zone_name");
                       $this->CI->db->where('country_code', $cp_time_zone['country_code']);
                       $zone_name = $this->CI->db->get("zone")->row_array();

                      return $zone_name['zone_name'];
                   }

         
    }
     

}
?>