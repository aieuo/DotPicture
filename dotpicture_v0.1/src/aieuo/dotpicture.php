<?php
namespace aieuo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;

use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class dotpicture extends PluginBase implements Listener{

    public function onEnable(){
            $this->getServer()->getPluginManager()->registerEvents($this,$this);
            if(!file_exists($this->getDataFolder())){
            	@mkdir($this->getDataFolder(), 0721, true);
			    @mkdir($this->getDataFolder()."\photos", 0721,true);
			    @mkdir($this->getDataFolder()."\\new_photos", 0721,true);
			    @mkdir($this->getDataFolder()."\createpng", 0721,true);
			}
    }

    public function onBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		if($player->getInventory()->getItemInHand()->getID() == 294 and $player->isOp()){
			$block = $event->getBlock();
			$this->pos[$player->getName()] = [round($block->x), round($block->y), round($block->z),$block->level];
			$pos = $this->pos[$player->getName()];
			$player->sendMessage("設定しました (".$pos[0].", ".$pos[1].", ".$pos[2].", ".$pos[3]->getFolderName().")");
			$event->setCancelled();
		}
	}

	public function onCommand(CommandSender $sender, Command $command,string $label, array $args):bool{
		$cmd = $command->getName();
		$name = $sender->getName();
		if($cmd == "wall"){
			if(!isset($args[0]))return false;
			if($name == "CONSOLE")return false;
			switch ($args[0]) {
				case 'list':
					$dir = $this->getDataFolder()."photos/";
					$data = [
						"type" => "form",
						"title" => "§lファイル",
						"content" => "§7ボタンを押してください",
						"buttons" => []
					];
					unset($this->files[$name]["list"]);
					if( is_dir($dir) && $handle = opendir($dir) ) {
						while( ($file = readdir($handle)) !== false ) {
							if( filetype( $path = $dir."".$file ) == "file" ) {
								if(substr($file,-4) == ".png"){
									$data["buttons"][] = [
										"text" => $file
									];
									$this->files[$name]["list"][] = $file;
								}
							}
						}
					}
					$pk = new ModalFormRequestPacket();
					$pk->formId = 543210;
					$pk->formData = json_encode($data);
					$sender->dataPacket($pk);
					return true;
					break;
				case 'create':
					$dir = $this->getDataFolder()."photos/";
					if (!isset($args[1]) or !isset($args[2]) or !isset($args[3])) {
						return false;
					}
					if(!strpos($args[1],".png")!==false){
						$aaa = $args[1];
						$args[1] = $args[1].".png";
					}
					if (!file_exists($dir."".$args[1])){
						$sender->sendMessage("そんな画像はありません");
						if(strpos($aaa,".")!==false){
							$sender->sendMessage("拡張子が.png以外の可能性があります\npng形式に変換して下さい");
						}
						return true;
					}
					if(!isset($this->pos[$name])){
						$sender->sendMessage("まずposを設定してください");
						return true;
					}
					$a = $this->resize($args[1],$args[2],$args[3]);
					if($a == false){
						$sender->sendMessage("error");
						return true;
					}
					$b = $this->CheckColor($a["path"],$a["w"],$a["h"]);
					$this->create($this->pos[$name],$b,$sender,$a["w"],$a["h"]);
					return true;
					break;
				
				default:
					return false;
					break;
			}
		}
	}

	public function onReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if (!($pk instanceof ModalFormResponsePacket)) return;
		$player = $event->getPlayer();
		$name = $player->getName();
		$id = $pk->formId;
		$data = json_decode($pk->formData);
		if($id == 543210){
			if($data === null)return;
			$filename = $this->files[$name]["list"][$data];
			$fdata = [
				"type" => "form",
				"title" => "§l".$filename,
				"content" => "§7ボタンを押してください",
				"buttons" => [
					[
						"text" => "これを作る"
					],
					[
						"text" => "サイズ変更 だけ"
					],
					[
						"text" => "名前を変える"
					],
					[
						"text" => "§c削除する"
					]
				]
			];
			$pk = new ModalFormRequestPacket();
			$pk->formId = 543211;
			$pk->formData = json_encode($fdata);
			$player->dataPacket($pk);
			$this->files[$name]["name"] = $filename;
		}
		if($id == 543211){
			if($data === null)return;
			switch ($data) {
				case 0:
					if(!isset($this->pos[$name])){
						$player->sendMessage("まずposを設定してください");
						return;
					}
					$fdata = [
						"type" => "custom_form",
						"title" => "",
						"content" => [
							[
								"type" => "label",
								"text" => "大きさの設定\nper をつけるとその比率になります\n片方をautoにすると元画像と同じ縦横の比になります"
							],
							[
								"type" => "input",
								"text" => "横",
								"placeholder" => "100  |  50per(半分)"
							],
							[
								"type" => "input",
								"text" => "縦",
								"placeholder" => "200  |  50per"
							],
						]
					];
					$pk = new ModalFormRequestPacket();
					$pk->formId = 543215;
					$pk->formData = json_encode($fdata);
					$player->dataPacket($pk);
					break;
				case 1:
					$fdata = [
						"type" => "custom_form",
						"title" => "ファイル名変更",
						"content" => [
							[
								"type" => "label",
								"text" => "大きさの設定\nper をつけるとその比率になります\n片方をautoにすると元画像と同じ縦横の比になります"
							],
							[
								"type" => "input",
								"text" => "横",
								"placeholder" => "100  |  50per(半分)"
							],
							[
								"type" => "input",
								"text" => "縦",
								"placeholder" => "200  |  50per"
							],
						]
					];
					$pk = new ModalFormRequestPacket();
					$pk->formId = 543214;
					$pk->formData = json_encode($fdata);
					$player->dataPacket($pk);
					break;
				case 2:
					$fdata = [
						"type" => "custom_form",
						"title" => "ファイル名変更",
						"content" => [
							[
								"type" => "input",
								"text" => "",
								"default" => str_replace(".png","",$this->files[$name]["name"])
							]
						]
					];
					$pk = new ModalFormRequestPacket();
					$pk->formId = 543212;
					$pk->formData = json_encode($fdata);
					$player->dataPacket($pk);
					break;
				case 3:
					$fdata = [
						"type" => "modal",
						"title" => "§l[".$this->files[$name]["name"]."]§0削除",
						"content" => "§c本当に削除しますか?",
						"button1" => "する",
						"button2" => "しない"
					];
					$pk = new ModalFormRequestPacket();
					$pk->formId = 543213;
					$pk->formData = json_encode($fdata);
					$player->dataPacket($pk);
					break;
			}
		}
		if($id == 543212){
			if($data === null)return;
			$dir = $this->getDataFolder()."photos/";
			rename($dir."".$this->files[$name]["name"],$dir."".$data[0].".png");
			$player->SendMessage("変更しました");
		}
		if($id == 543213){
			if($data === null)return;
			$dir = $this->getDataFolder()."photos/";
			if($data == true){
				unlink($dir."".$this->files[$name]["name"]);
				$player->sendMessage("削除しました");
			}else{
				$player->sendMessage("キャンセルしました");
			}
		}
		if($id == 543214){
			if($data === null)return;
			if($data[1] == "" or $data[2] == ""){
				$player->SendMessage("必要事項を記入してください");
				return;
			}
			$a = $this->resize($this->files[$name]["name"],$data[1],$data[2]);
			if($a == false){
				$player->sendMessage("error");
			}else{
				$player->sendMessage("作成しました");
			}
		}
		if($id == 543215){
			if($data === null)return;
			if($data[1] == "" or $data[2] == ""){
				$player->SendMessage("必要事項を記入してください");
				return;
			}
			$a = $this->resize($this->files[$name]["name"],$data[1],$data[2]);
			if($a == false){
				$player->sendMessage("error");
				return;
			}
			$b = $this->CheckColor($a["path"],$a["w"],$a["h"]);
			$this->create($this->pos[$name],$b,$player,$a["w"],$a["h"]);
		}
	}

	public function resize($name,$w,$h){
		$opath = $this->getDataFolder()."photos/";
		$npath = $this->getDataFolder()."new_photos/";
		$file = $opath."".$name;
		list($oldw, $oldh) = getimagesize($file);
		$image = imagecreatefrompng($file);
		if($w == "auto"){
			$h = (int)$h;
			if($h == 0){
				return false;
			}
			$w = ceil($oldw / $oldh * $h);;
		}
		if($h == "auto"){
			$w = (int)$w;
			if($w == 0){
				return false;
			}
			$h = ceil($oldh / $oldw * $w);;
		}
		if(strpos($w,"per") !== false){
			$w = ceil($oldw /100 * (int)str_replace("per","",$w));
		}else{
			$w = (int)$w;
		}
		if(strpos($h,"per") !== false){
			$h = ceil($oldh /100 * (int)str_replace("per","",$h));
		}else{
			$h = (int)$h;
		}
		if($w == 0 or $h == 0){
			return false;
		}
		$canvas = imagecreatetruecolor($w, $h);
		imagecopyresampled($canvas, $image, 0,0,0,0, $w, $h, $oldw, $oldh);
		$resize_path = $npath."".str_replace(".png","",$name)."_".$w.",".$h.".png";
		imagepng($canvas, $resize_path, 9);
		$datas = [
			"path" => $resize_path,
			"w" => $w,
			"h" => $h
		];
		return $datas;
	}

	public function CheckColor($name,$w,$h){
		$dir = $this->getDataFolder()."new_photos/";
		$img = imagecreatefrompng($name);
		$imagex = imagesx($img);
		$imagey = imagesy($img);
		$red = [150,110,110];
		$green = [110, 110, 110];
		$white = [110,110,110];
		for($y = 0; $y < $imagey;$y++){
			for($x = 0; $x < $imagex; $x++){
				$rgb = imagecolorat($img, $x, $y);
				$colors = imagecolorsforindex($img, $rgb);
				$r = $colors['red'];
				$g = $colors['green'];
				$b = $colors['blue'];

				$color[0] = [250,0,0];//red
				$color[1] = [0,128,0];//green
				$color[2] = [0,0,250];//blue
				unset($num1,$res1);
				for($i = 0;$i <= 2;$i++){
					$res1[$i] = (sqrt(pow($color[$i][0]-$r,2)))+(sqrt(pow($color[$i][1]-$g,2)))+(sqrt(pow($color[$i][2]-$b,2)));
					if(!isset($num1[$res1[$i]])){
						$num1[$res1[$i]] = $i;
					}
				}
				$min1 = min($res1);
				$n1 = $num1[$min1];
				if($n1 == 0){//red
					$ncolor[0] = [255,255,255];//white 0
					$ncolor[1] = [255,165,0];//orange 1
					$ncolor[2] = [255,0,255];//magenta 2
					$ncolor[3] = [255,215,0];//yellow 4
					$ncolor[4] = [255,105,180];//pink 6
					$ncolor[5] = [128,128,128];//gray 7
					$ncolor[6] = [88,35,10];//brown 12
					$ncolor[7] = [220,0,0];//red 14
					$ncolor[8] = [0,0,0];//block 15
					$ncolor[9] = [255,235,205];//whert 24
					$nc = 10;
				}elseif($n1 == 1){//green
					$ncolor[0] = [255,255,255];//white 0
					$ncolor[1] = [255,215,0];//yellow 4
					$ncolor[2] = [0,255,0];//lime 5
					$ncolor[3] = [128,128,128];//gray 7
					$ncolor[4] = [0,255,255];//cyan 9
					$ncolor[5] = [0,128,0];//green 13
					$ncolor[6] = [0,0,0];//block 15
					$nc = 7;
				}elseif($n1 == 2){//blue
					$ncolor[0] = [255,255,255];//white 0
					$ncolor[1] = [255,0,255];//magenta 2
					$ncolor[2] = [173,216,230];//lightblue 3
					$ncolor[3] = [128,128,128];//gray 7
					$ncolor[4] = [112,128,144];//lightgray 8
					$ncolor[5] = [0,255,255];//cyan 9
					$ncolor[6] = [135,0,204];//purple 10
					$ncolor[7] = [0,0,255];//blue 11
					$ncolor[8] = [0,0,0];//block 15
					$nc = 9;
				}
				unset($num2,$res2);
				for($i = 0;$i < $nc;$i++){
					$res2[$i] = (sqrt(pow($ncolor[$i][0]-$r,2)))+(sqrt(pow($ncolor[$i][1]-$g,2)))+(sqrt(pow($ncolor[$i][2]-$b,2)));
					$num2[$res2[$i]] = $i;
				}
				$min2 = min($res2);
				$n2 = $num2[$min2];
				if($n1 == 0){
					if($n2 == 0){
						$iiro = 35;
						$diro = 0;
					}elseif($n2 == 1){
						$iiro = 35;
						$diro = 1;
					}elseif($n2 == 2){
						$iiro = 35;
						$diro = 2;
					}elseif($n2 == 3){
						$iiro = 35;
						$diro = 4;
					}elseif($n2 == 4){
						$iiro = 35;
						$diro = 6;
					}elseif($n2 == 5){
						$iiro = 35;
						$diro = 7;
					}elseif($n2 == 6){
						$iiro = 35;
						$diro = 12;
					}elseif($n2 == 7){
						$iiro = 35;
						$diro = 14;
					}elseif($n2 == 8){
						$iiro = 35;
						$diro = 15;
					}elseif($n2 == 9){
						$iiro = 24;
						$diro = 0;
					}
				}elseif($n1 == 1){
					if($n2 == 0){
						$iiro = 35;
						$diro = 0;
					}elseif($n2 == 1){
						$iiro = 35;
						$diro = 4;
					}elseif($n2 == 2){
						$iiro = 35;
						$diro = 5;
					}elseif($n2 == 3){
						$iiro = 35;
						$diro = 7;
					}elseif($n2 == 4){
						$iiro = 35;
						$diro = 9;
					}elseif($n2 == 5){
						$iiro = 35;
						$diro = 13;
					}elseif($n2 == 6){
						$iiro = 35;
						$diro = 15;
					}
				}elseif($n1 == 2){
					if($n2 == 0){
						$iiro = 35;
						$diro = 0;
					}elseif($n2 == 1){
						$iiro = 35;
						$diro = 2;
					}elseif($n2 == 2){
						$iiro = 35;
						$diro = 3;
					}elseif($n2 == 3){
						$iiro = 35;
						$diro = 7;
					}elseif($n2 == 4){
						$iiro = 35;
						$diro = 8;
					}elseif($n2 == 5){
						$iiro = 35;
						$diro = 9;
					}elseif($n2 == 6){
						$iiro = 35;
						$diro = 10;
					}elseif($n2 == 7){
						$iiro = 35;
						$diro = 11;
					}elseif($n2 == 8){
						$iiro = 35;
						$diro = 15;
					}
				}
				$hy = $h -1 - $y;
				$hx = $w -1 - $x;
				$datas[$hx][$hy]["id"] = $iiro;
				$datas[$hx][$hy]["da"] = $diro;
			}
		}
		return $datas;
	}

	public function create($pos, $datas, $player,$w,$h,$i1 = -1, $i2 = 0){
		$level = $pos[3];
		for($n = 0; $n < 40; $n++){
			$i1 ++;
			if($i1 >= $h){
				$i1 = 0;
				$i2 ++;
			}
			if($i2 >= $w){
				$player->sendMessage("完了しました");
				return;
			}
			$y = $pos[1] + $i1;
			$x = $pos[0] + $i2;
			$z = $pos[2];
			$id = $datas[$i2][$i1]["id"];
			$meta = $datas[$i2][$i1]["da"];
			$level->setBlock(new Vector3($x,$y,$z),Block::get($id,$meta),false);
		}
		$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "create"], [$pos, $datas, $player,$w,$h,$i1, $i2]),1);
	}
}