<?php

namespace ifteam\TAGBlock;

use pocketmine\level\Position;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\Server;
use ifteam\TAGBlock\task\DeleteParticleTask;

class TAGSystem {
	private static $instance = null;
	private $plugin;
	public function __construct(TAGBlock $plugin) {
		if (self::$instance === null)
			self::$instance = $this;
		$this->plugin = $plugin;
	}
	/**
	 * 인스턴스를 반환합니다.
	 *
	 * @return TAGSystem
	 */
	public static function getInstance() {
		return self::$instance;
	}
	/**
	 * 태그를 추가합니다.
	 *
	 * @param Position $pos        	
	 * @param string $message        	
	 */
	public function addTag(Position $pos, $message) {
		$this->plugin->getDb ()->db ["TAGBlock"] [$pos->getLevel ()->getFolderName ()] [( int ) $pos->x . "." . ( int ) $pos->y . "." . ( int ) $pos->z] = $message;
	}
	/**
	 * 잠시동안만 출력되는 태그를 추가합니다.
	 * 20tick = 1초(sec) 입니다.
	 *
	 * @param Position $pos        	
	 * @param string $message        	
	 * @param int $tick        	
	 */
	public function addInstanceTag(Position $pos, $message, $tick = 60) {
		$particle = new FloatingTextParticle ( $pos, "", $message );
		$pos->getLevel ()->addParticle ( $particle );
		Server::getInstance ()->getScheduler ()->scheduleDelayedTask ( new DeleteParticleTask ( $particle, $pos->getLevel () ), $tick );
	}
	/**
	 * 태그를 삭제합니다.
	 *
	 * @param Position $pos        	
	 */
	public function deleteTag(Position $pos) {
		if ($this->isTAGBlockExist ( $pos ))
			unset ( $this->plugin->getDb ()->db ["TAGBlock"] [$pos->getLevel ()->getFolderName ()] [( int ) $pos->x . "." . ( int ) $pos->y . "." . ( int ) $pos->z] );
	}
	/**
	 * 해당위치에 태그가 있는지 확인합니다.
	 *
	 * @param Position $pos        	
	 */
	public function isTagExist(Position $pos) {
		return isset ( $this->plugin->getDb ()->db ["TAGBlock"] [$pos->getLevel ()->getFolderName ()] [( int ) $pos->x . "." . ( int ) $pos->y . "." . ( int ) $pos->z] );
	}
}

?>