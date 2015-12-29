<?php

namespace ifteam\TAGBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\entity\Item as ItemEntity;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Level;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerQuitEvent;
use ifteam\TAGBlock\task\TAGBlockTask;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use ifteam\TAGBlock\task\TAGBlockAsyncSearchTask;
use ifteam\TAGBlock\database\PluginData;
use pocketmine\Player;
use pocketmine\block\Block;

/*
 * TODO
 * 인스턴스 태그 (*파티클이용)
 *
 */
class TAGBlock extends PluginBase implements Listener {
	private $db;
	private $tagSystem;
	private $showed;
	private $packet = [ ];
	public function onEnable() {
		$this->db = new PluginData ( $this );
		$this->tagSystem = new TAGSystem ( $this );
		
		/* 패킷을 사전에 정의-초기화 해놓습니다 */
		$this->initPackets ();
		
		/* 명령어를 등록합니다 */
		$this->db->registerCommand ( "tagblock", "tagblock.add", $this->db->get ( "TAGBlock-description" ), $this->db->get ( "TAGBlock-command-help" ) );
		
		/* pushSearchProcess() 를 주기적으로 실행 */
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new TAGBlockTask ( $this ), 60 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->db->save ();
	}
	/**
	 * 비동기로 유저에게 표시할 태그목록을 찾게합니다.
	 */
	public function pushSearchProcess() {
		if (count ( $this->db->db ["TAGBlock"] ) == 0 and count ( $this->showed ) == 0)
			return;
		$players = array ();
		
		foreach ( $this->getServer ()->getOnlinePlayers () as $onlinePlayer ) {
			$player = array ();
			$player ["name"] = $onlinePlayer->getName ();
			$player ["level"] = $onlinePlayer->getLevel ()->getFolderName ();
			$player ["x"] = $onlinePlayer->x;
			$player ["y"] = $onlinePlayer->y;
			$player ["z"] = $onlinePlayer->z;
			$players [] = $player;
		}
		
		$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new TAGBlockAsyncSearchTask ( $players, $this->db->db ["TAGBlock"], $this->showed ) );
	}
	/**
	 * 비동기로 찾은 유저에게 표시할 태그목록을 받아서 처리합니다.
	 *
	 * @param array $players        	
	 * @param array $results        	
	 */
	public function receiveSearchProcess($players, $needToAdd, $needToRemove) {
		foreach ( $players as $player ) {
			$player = $this->getServer ()->getPlayer ( $player );
			if (! $player instanceof Player)
				continue;
			if (isset ( $needToAdd [$player->getName ()] )) {
				foreach ( $needToAdd [$player->getName ()] as $tagPos ) {
					/* 유저 패킷을 상점밑에 보내서 네임택 출력 */
					if (isset ( $this->showed [$player->getName ()] [$tagPos] ))
						continue;
					
					$this->showed [$player->getName ()] [$tagPos] = Entity::$entityCount ++;
					
					$packet = $this->getAddEntityPacket ();
					$packet->eid = $this->showed [$player->getName ()] [$tagPos];
					$packet->metadata [Entity::DATA_NAMETAG] = [ 
							Entity::DATA_TYPE_STRING,
							$this->db->db ["TAGBlock"] [$player->getLevel ()->getFolderName ()] [$tagPos] 
					];
					
					$explodePos = explode ( ".", $tagPos );
					$packet->x = $explodePos [0] + 0.4;
					$packet->y = $explodePos [1] - 0.4;
					$packet->z = $explodePos [2] + 0.4;
					
					$player->dataPacket ( $packet );
				}
			}
			if (isset ( $needToRemove [$player->getName ()] )) {
				foreach ( $needToRemove [$player->getName ()] as $tagPos ) {
					/* 표시범위에서 벗어난 태그 출력해제 */
					$packet = $this->getRemoveEntityPacket ();
					$packet->eid = $this->showed [$player->getName ()] [$tagPos];
					$player->dataPacket ( $packet );
					unset ( $this->showed [$player->getName ()] [$tagPos] );
				}
			}
		}
	}
	/**
	 * 표지판으로 태그블럭을 설치가능하게 이벤트처리
	 *
	 * @param SignChangeEvent $event        	
	 */
	public function onSignChangeEvent(SignChangeEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "tagblock.add" ))
			return;
		if (strtolower ( $event->getLine ( 0 ) ) != $this->db->get ( "TAGBlock-line0" ))
			return;
		
		if ($event->getLine ( 1 ) != null)
			$message = $event->getLine ( 1 );
		if ($event->getLine ( 2 ) != null)
			$message .= "\n" . $event->getLine ( 2 );
		if ($event->getLine ( 3 ) != null)
			$message .= "\n" . $event->getLine ( 3 );
		
		$block = $event->getBlock ()->getSide ( 0 );
		$blockPos = "{$block->x}.{$block->y}.{$block->z}";
		
		$this->db->db ["TAGBlock"] [$block->getLevel ()->getFolderName ()] [$blockPos] = $message;
		$this->db->message ( $event->getPlayer (), $this->db->get ( "TAGBlock-added" ) );
		
		$event->setCancelled ();
		$event->getBlock ()->getLevel ()->setBlock ( $event->getBlock (), Block::get ( Block::AIR ) );
	}
	/**
	 * 명령어 처리
	 *
	 * {@inheritDoc}
	 *
	 * @see \pocketmine\plugin\PluginBase::onCommand()
	 */
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case "tagblock" :
				if (! isset ( $args [4] ) or ! is_numeric ( $args [1] ) or ! is_numeric ( $args [2] ) or ! is_numeric ( $args [3] )) {
					$this->db->message ( $player, $this->db->get ( "TAGBlock-command-help" ) );
					return true;
				}
				$level = $this->getServer ()->getLevelByName ( $args [0] );
				if (! $level instanceof Level) {
					$this->db->message ( $player, $this->db->get ( "TAGBlock-level-doesnt-exist" ) );
					return true;
				}
				$blockPos = "{$args [1]}.{$args [2]}.{$args [3]}";
				
				$message = $args;
				array_shift ( $message );
				array_shift ( $message );
				array_shift ( $message );
				array_shift ( $message );
				$message = implode ( " ", $message );
				
				$lines = explode ( "\\n", $message );
				$message = "";
				foreach ( $lines as $line )
					$message .= $line . "\n";
				
				$this->db->db ["TAGBlock"] [$level->getFolderName ()] [$blockPos] = $message;
				$this->db->message ( $player, $this->db->get ( "TAGBlock-added" ) );
				break;
		}
		return true;
	}
	/**
	 * 네임택이 있는 블럭파괴시 네임택을 제거하게 합니다.
	 *
	 * @param BlockBreakEvent $event        	
	 */
	public function onBlockBreakEvent(BlockBreakEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "tagblock.add" ))
			return;
		
		$block = $event->getBlock ();
		$blockPos = "{$block->x}.{$block->y}.{$block->z}";
		
		if (! isset ( $this->db->db ["TAGBlock"] [$block->level->getFolderName ()] [$blockPos] ))
			return;
		
		if (isset ( $this->showed [$event->getPlayer ()->getName ()] [$blockPos] )) {
			/* 파괴된 태그의 제거패킷을 전송합니다 */
			$packet = $this->getRemoveEntityPacket ();
			$packet->eid = $this->showed [$event->getPlayer ()->getName ()] [$blockPos];
			$event->getPlayer ()->dataPacket ( $packet );
		}
		
		unset ( $this->db->db ["TAGBlock"] [$block->level->getFolderName ()] [$blockPos] );
		$this->db->message ( $event->getPlayer (), $this->db->get ( "TAGBlock-deleted" ) );
	}
	/**
	 * 플러그인 데이터베이스를 반환합니다.
	 */
	public function getDb() {
		return $this->db;
	}
	/**
	 * 유저가 접속종료시 보여준 태그블럭 목록을 초기화하도록 이벤트처리
	 *
	 * @param PlayerQuitEvent $event        	
	 */
	public function onPlayerQuitEvent(PlayerQuitEvent $event) {
		if (isset ( $this->showed [$event->getPlayer ()->getName ()] ))
			unset ( $this->showed [$event->getPlayer ()->getName ()] );
	}
	/**
	 * 사전정의된 AddEntityPacket 를 가져옵니다
	 *
	 * @return AddEntityPacket
	 */
	public function getAddEntityPacket() {
		return $this->packet ["AddEntityPacket"];
	}
	/**
	 * 사전정의된 RemoveEntityPacket 를 가져옵니다
	 *
	 * @return RemoveEntityPacket
	 */
	public function getRemoveEntityPacket() {
		return $this->packet ["AddEntityPacket"];
	}
	/**
	 * 사용할 패킷을 사전에 정의해놓습니다.
	 */
	public function initPackets() {
		$this->packet ["AddEntityPacket"] = new AddEntityPacket ();
		$this->packet ["AddEntityPacket"]->eid = 0;
		$this->packet ["AddEntityPacket"]->type = ItemEntity::NETWORK_ID;
		$this->packet ["AddEntityPacket"]->x = 0;
		$this->packet ["AddEntityPacket"]->y = 0;
		$this->packet ["AddEntityPacket"]->z = 0;
		$this->packet ["AddEntityPacket"]->speedX = 0;
		$this->packet ["AddEntityPacket"]->speedY = 0;
		$this->packet ["AddEntityPacket"]->speedZ = 0;
		$this->packet ["AddEntityPacket"]->yaw = 0;
		$this->packet ["AddEntityPacket"]->pitch = 0;
		$this->packet ["AddEntityPacket"]->item = 0;
		$this->packet ["AddEntityPacket"]->meta = 0;
		$this->packet ["AddEntityPacket"]->metadata = [ 
				Entity::DATA_FLAGS => [ 
						Entity::DATA_TYPE_BYTE,
						1 << Entity::DATA_FLAG_INVISIBLE 
				],
				Entity::DATA_NAMETAG => [ 
						Entity::DATA_TYPE_STRING,
						"" 
				],
				Entity::DATA_SHOW_NAMETAG => [ 
						Entity::DATA_TYPE_BYTE,
						1 
				],
				Entity::DATA_NO_AI => [ 
						Entity::DATA_TYPE_BYTE,
						1 
				],
				Entity::DATA_AIR => [ 
						Entity::DATA_TYPE_SHORT,
						10 
				] 
		];
		
		$this->packet ["RemoveEntityPacket"] = new RemoveEntityPacket ();
	}
}

?>