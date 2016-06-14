<?php

/**
 * @file
 * Contains CVestaAPI class - class for domain, database and ftp account creation using VESTA API.
 */
class CVestaAPI
{

    /**
     * Host.
     * @var string
     */
    private $vst_hostname;

    /**
     * Server username.
     * @var string
     */
    private $vst_username;

    /**
     * Password to server.
     * @var string
     */
    private $vst_password;

    /**
     * Database host.
     * @var string
     */
    private $vst_dbhostname;

    /**
     * API response codes.
     * @var array
     */
    private $vst_errors = array(
      1 => 'Not enough arguments provided',
      2 => 'Object or argument is not valid',
      3 => 'Object doesn\'t exist',
      4 => 'Object already exists',
      5 => 'Object is suspended',
      6 => 'Object is already unsuspended',
      7 => "Object can't be deleted because is used by the other object",
      8 => 'Object cannot be created because of hosting package limits',
      9 => 'Wrong password',
      10 => 'Object cannot be accessed be the user',
      11 => 'Subsystem is disabled',
      12 => 'Configuration is broken',
      13 => 'Not enough disk space to complete the action',
      14 => 'Server is to busy to complete the action',
      15 => 'Connection failed. Host is unreachable',
      16 => 'FTP server is not responding',
      17 => 'Database server is not responding',
      18 => 'RRDtool failed to update the database',
      19 => 'Update operation failed',
      20 => 'Service restart failed'
    );


    /**
     * CVestaAPI constructor.
     * @param $vst_hostname
     * @param $vst_username
     * @param $vst_password
     * @param $vst_dbhostname
     */
    public function __construct($vst_hostname, $vst_username, $vst_password, $vst_dbhostname)
    {
        $this->vst_hostname = $vst_hostname;
        $this->vst_username = $vst_username;
        $this->vst_password = $vst_password;
        $this->vst_dbhostname = $vst_dbhostname;
    }

    /**
     * Create all data.
     * @param array $data
     *
     * @return array
     */
    public function createData($data = array())
    {
        $messages = array();
        $email_body = '';
        $domain = '';

        if (empty($data['login'])) {
            return $messages['danger'][] = 'Enter user login.';
        }

        if (!empty($data['login'])) {
            if (!empty($data['domain'])) {
                $domain = $data['domain'] . $data['domain_postfix'];

                // Create domain.
                $answer_domain = $this->createDomain($data['login'], $domain);

                // Check result.
                if ($answer_domain === '0') {
                    $messages['success'][] = 'Domain was created successfully.';
                } else {
                    $error = isset($this->vst_errors[$answer_domain]) ? $this->vst_errors[$answer_domain] : 'Unknown error';
                    $messages['danger'][] = 'Domain wasn\'t created, error: ' . $error;
                }

                // Create FTP.
                if (!empty($data['ftp_username']) && !empty($data['ftp_pass'])) {
                    $answer_ftp = $this->createFTP($data['login'], $domain, $data['ftp_username'], $data['ftp_pass'],
                      $data['ftp_path']);

                    if ($answer_ftp === '0') {
                        $messages['success'][] = 'FTP was created successfully.';

                        $email_body .= '<p>FTP was created. <br/>';
                        $email_body .= 'Host: ' . $this->vst_hostname . "\n";
                        $email_body .= 'Username: ' . $data['login'] . '_' . $data['ftp_username'] . '<br/>';
                        $email_body .= 'Password: ' . $data['ftp_pass'] . '<br/>';
                        $email_body .= 'Folder: ' . $data['ftp_prepath'] . '/' . $data['ftp_path'] . '</p>';

                    } else {
                        $error = isset($this->vst_errors[$answer_ftp]) ? $this->vst_errors[$answer_ftp] : 'Unknown error';
                        $messages['danger'][] = 'FTP wasn\'t created, error: ' . $error;
                    }
                } else {
                    $messages['danger'][] = 'FTP wasn\'t created, please check ftp username and password.';
                }
            } else {
                $messages['danger'][] = 'Please enter domain name.';
            }

            // Create DB.
            if (!empty($data['db_name']) && !empty($data['db_user']) && !empty($data['db_pass'])) {
                $answer_db = $this->createDB($data['login'], $data['db_name'], $data['db_user'], $data['db_pass']);
                if ($answer_db === '0') {
                    $messages['success'][] = 'Database was created successfully.';

                    $email_body .= '<p>Database was created successfully.<br/>';
                    $email_body .= 'Host: ' . $this->vst_dbhostname . '<br/>';
                    $email_body .= 'Database: ' . $data['db_name'] . '<br/>';
                    $email_body .= 'DB Username: ' . $data['db_user'] . '<br/>';
                    $email_body .= 'DB password: ' . $data['db_pass'] . '</p>';
                } else {
                    $error = isset($this->vst_errors[$answer_db]) ? $this->vst_errors[$answer_db] : 'Unknown error';
                    $messages['danger'][] = 'Database wasn\'t created, error: ' . $error;
                }
            } else {
                $messages['danger'][] = 'Database wasn\'t created, please check database name, username and password.';
            }

            if (!empty($email_body)) {
                // Send email with the access.
                $subject = 'Access for ' . $domain;

                $message = $this->sendMail($data['email'], $subject, $email_body);

                if (isset($message['success'])) {
                    $messages['success'][] = $message['success'];
                } elseif (isset($message['danger'])) {
                    $messages['danger'][] = $message['danger'];
                }
            }

        }

        return $messages;
    }

    /**
     * Create domain.
     * @param $username
     * @param $domain
     *
     * @return string
     */
    public function createDomain($username, $domain)
    {
        $vst_returncode = 'yes';
        $vst_command = 'v-add-domain';

        // Prepare POST query.
        $post_vars = array(
          'user' => $this->vst_username,
          'password' => $this->vst_password,
          'returncode' => $vst_returncode,
          'cmd' => $vst_command,
          'arg1' => $username,
          'arg2' => $domain
        );

        $answer = $this->sendRequest($post_vars);

        return $answer;
    }

    /**
     * Create database.
     * @param $username
     * @param $db_name
     * @param $db_user
     * @param $db_pass
     *
     * @return string
     */
    public function createDB($username, $db_name, $db_user, $db_pass)
    {
        $vst_returncode = 'yes';
        $vst_command_db = 'v-add-database';

        // Prepare POST query.
        $post_vars = array(
          'user' => $this->vst_username,
          'password' => $this->vst_password,
          'returncode' => $vst_returncode,
          'cmd' => $vst_command_db,
          'arg1' => $username,
          'arg2' => $db_name,
          'arg3' => $db_user,
          'arg4' => $db_pass
        );


        $answer = $this->sendRequest($post_vars);

        return $answer;
    }

    /**
     * Create FTP account.
     * @param $username
     * @param $domain
     * @param $ftp_username
     * @param $ftp_pass
     * @param $ftp_path
     *
     * @return mixed
     */
    public function createFTP($username, $domain, $ftp_username, $ftp_pass, $ftp_path)
    {
        $vst_returncode = 'yes';
        $vst_command = 'v-add-web-domain-ftp';

        // Prepare POST query
        $post_vars = array(
          'user' => $this->vst_username,
          'password' => $this->vst_password,
          'returncode' => $vst_returncode,
          'cmd' => $vst_command,
          'arg1' => $username,
          'arg2' => $domain,
          'arg3' => $ftp_username,
          'arg4' => $ftp_pass,
          'arg5' => $ftp_path
        );

        $answer = $this->sendRequest($post_vars);

        return $answer;
    }


    /**
     * Send email with access.
     * @param $email
     * @param $subject
     * @param $body
     * @param $headers
     * @return array
     */
    protected function sendMail($email, $subject, $body, $headers = '')
    {
        $messages = array();

        if (!mail($email, $subject, $body, $headers)) {
            $messages['danger'] = 'Email can not be sent.';
        } else {
            $messages['success'] = 'Email with access was sent.';
        }

        return $messages;
    }

    /**
     * Send curl request.
     * @param $post_vars
     *
     * @return mixed
     */
    private function sendRequest($post_vars)
    {
        $post_data = http_build_query($post_vars);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://' . $this->vst_hostname . ':8083/api/');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        $answer = curl_exec($curl);

        return $answer;
    }
} 