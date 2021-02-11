<?php
declare(strict_types=1);

namespace kim\present\batchfarming\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class BatchFarmingStartEvent extends Event implements Cancellable{
    use CancellableTrait;

    private Player $player;
    private Item $item;

    private int $maxStep;
    private float $risePerStep;
    private bool $clockwise;

    public function __construct(Player $player, Item $item, int $maxStep, float $risePerStep, bool $clockwise){
        $this->player = $player;
        $this->item = $item;
        $this->maxStep = $maxStep;
        $this->risePerStep = $risePerStep;
        $this->clockwise = $clockwise;
    }

    public function getPlayer() : Player{
        return $this->player;
    }

    public function getItem() : Item{
        return $this->item;
    }

    public function getMaxStep() : int{
        return $this->maxStep;
    }

    public function setMaxStep(int $maxStep) : void{
        $this->maxStep = $maxStep;
    }

    public function getRisePerStep() : float{
        return $this->risePerStep;
    }

    public function setRisePerStep(float $risePerStep) : void{
        $this->risePerStep = $risePerStep;
    }

    public function isClockwise() : bool{
        return $this->clockwise;
    }

    public function setClockwise(bool $clockwise) : void{
        $this->clockwise = $clockwise;
    }
}