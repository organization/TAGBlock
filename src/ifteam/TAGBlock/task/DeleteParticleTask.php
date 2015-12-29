<?php

namespace ifteam\TAGBlock\task;

use pocketmine\scheduler\Task;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\level\Level;

class DeleteParticleTask extends Task {
	public $particle;
	public $level;
	public function __construct(FloatingTextParticle $particle, Level $level) {
		$this->particle = $particle;
		$this->level = $level;
	}
	public function onRun($currentTick) {
		$this->particle->setInvisible ();
		$this->level->addParticle ( $this->particle );
	}
}

?>