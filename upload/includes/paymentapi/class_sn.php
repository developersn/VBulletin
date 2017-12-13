<?php
if (!isset($GLOBALS['vbulletin']->db)) exit;
class vB_PaidSubscriptionMethod_sn extends vB_PaidSubscriptionMethod
{
	var $supports_recurring = false;
	var $display_feedback = true;
	function verify_payment()
	{
		$this->registry->input->clean_array_gpc('r', array('item' => TYPE_STR));
		if(!empty($this->registry->GPC['item']))
		{
			$this->paymentinfo = $this->registry->db->query_first("SELECT paymentinfo.*, user.username FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid) WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['item']) . "'");
			if (!empty($this->paymentinfo))
			{
				$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
				$cost = unserialize($sub['cost']);
				$amount = intval(ceil($cost[0][cost][usd]*$this->settings['d2t']));
				
				
				@session_start();
				// Security
				$sec=$_GET['sec'];
				$mdback = md5($sec.'vm');
				$mdurl=$_GET['md'];
				// Security
								
				$transData = $_SESSION[$sec];
				$time = $transData['order_id'];
				$cost = $transData['price'];
				$au = $transData['au'];
				
                
				try
				{
					date_default_timezone_set("Asia/Tehran");
				
				// Security
				$sec=$_GET['sec'];
				$mdback = md5($sec.'vm');
				$mdurl=$_GET['md'];
				// Security
					if(isset($_GET['sec']) or isset($_GET['md']) AND $mdback == $mdurl )
					{
								
				$bank_return = $_POST + $_GET ;
				$data_string = json_encode(array (
				'pin' => $this->settings['plmid'],
				'price' => $cost,
				'order_id' => $time,
				'au' => $au,
				'bank_return' =>$bank_return,
				));

				$ch = curl_init('https://developerapi.net/api/v1/verify');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_string))
				);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 20);
				$result = curl_exec($ch);
				curl_close($ch);
				$json = json_decode($result,true);

														$res=$json['result'];
	                 switch ($res) {
						    case -1:
						    $msg = "پارامترهای ارسالی برای متد مورد نظر ناقص یا خالی هستند . پارمترهای اجباری باید ارسال گردد";
						    break;
						     case -2:
						    $msg = "دسترسی api برای شما مسدود است";
						    break;
						     case -6:
						    $msg = "عدم توانایی اتصال به گیت وی بانک از سمت وبسرویس";
						    break;
						     case -9:
						    $msg = "خطای ناشناخته";
						    break;
						     case -20:
						    $msg = "پین نامعتبر";
						    break;
						     case -21:
						    $msg = "ip نامعتبر";
						    break;
						     case -22:
						    $msg = "مبلغ وارد شده کمتر از حداقل مجاز میباشد";
						    break;
						    case -23:
						    $msg = "مبلغ وارد شده بیشتر از حداکثر مبلغ مجاز هست";
						    break;
						      case -24:
						    $msg = "مبلغ وارد شده نامعتبر";
						    break;
						      case -26:
						    $msg = "درگاه غیرفعال است";
						    break;
						      case -27:
						    $msg = "آی پی مسدود شده است";
						    break;
						      case -28:
						    $msg = "آدرس کال بک نامعتبر است ، احتمال مغایرت با آدرس ثبت شده";
						    break;
						      case -29:
						    $msg = "آدرس کال بک خالی یا نامعتبر است";
						    break;
						      case -30:
						    $msg = "چنین تراکنشی یافت نشد";
						    break;
						      case -31:
						    $msg = "تراکنش ناموفق است";
						    break;
						      case -32:
						    $msg = "مغایرت مبالغ اعلام شده با مبلغ تراکنش";
						    break;
						      case -35:
						    $msg = "شناسه فاکتور اعلامی order_id نامعتبر است";
						    break;
						      case -36:
						    $msg = "پارامترهای برگشتی بانک bank_return نامعتبر است";
						    break;
						        case -38:
						    $msg = "تراکنش برای چندمین بار وریفای شده است";
						    break;
						      case -39:
						    $msg = "تراکنش در حال انجام است";
						    break;
                            case 1:
						    $msg = "پرداخت با موفقیت انجام گردید.";
						    break;
						    default:
						       $msg = $josn['result'];
						}
				
                    if($json['result'] == 1)
					{
						$this->paymentinfo['currency'] = 'usd';
						$this->paymentinfo['amount'] = $cost[0][cost][usd];
						$this->type = 1;
						return true;
					}
					else
					{
						$this->error = 'خطایی رخ داده است : ('.$msg.')';
						return false;
					}
					
					}
					else
					{
						$this->error = 'خطایی رخ داده است : ('.$msg.')';
						return false;
					}
					
				}
				catch (SoapFault $ex)
				{
					exit ( 'Error: '.$ex->getMessage() );
				}
			}
		}
		$this->error = 'Duplicate transaction.';
		return false;
	}
	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo, $timeinfo)
	{
		global $vbphrase, $vbulletin, $show;
		$item = $hash;
		$cost = intval(ceil($cost*$this->settings['d2t']));
		$api = $this->settings['plmid'];
		$form['action'] = 'sn.php';
		$form['method'] = 'POST';
		$settings =& $this->settings;
		$templater = vB_Template::create('subscription_payment_sn');
		$templater->register('api', $api);
		$templater->register('cost', $cost);
		$templater->register('item', $item);
		$templater->register('subinfo', $subinfo);
		$templater->register('settings', $settings);
		$templater->register('userinfo', $userinfo);
		$form['hiddenfields'] .= $templater->render();
		return $form;
	}
}
?>