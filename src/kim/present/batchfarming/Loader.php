<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpDocSignatureInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace kim\present\batchfarming;

use kim\present\batchfarming\event\BatchFarmingStartEvent;
use kim\present\batchfarming\object\SeedObject;
use kim\present\batchfarming\task\SeedingTask;
use kim\present\traits\multilingualresource\MultilingualConfigTrait;
use pocketmine\block\Crops;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;

use function abs;
use function is_bool;
use function strtolower;

final class Loader extends PluginBase implements Listener{
    use MultilingualConfigTrait;

    private int $maxStep;
    private float $risePerStep;
    private bool $clockwise;

    protected function onEnable() : void{
        $this->maxStep = (int) $this->getConfigFloat("max-step", 32);
        $this->risePerStep = $this->getConfigFloat("rise-per-step", 32);
        $this->clockwise = $this->getConfigBool("clockwise", true);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @priority LOWEST
     *
     * Works when holding a crops item and sneaking + right click.
     * The crops are rotated and placed based on the touched block.
     * The number of steps, direction of rotation, and amount of rising are read from config.yml.
     */
    public function onPlayerInteract(PlayerInteractEvent $event) : void{
        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK)
            return;

        $player = $event->getPlayer();
        if(!$player->isSneaking() || SeedingTask::getCount($player) > 0)
            return;

        $item = $event->getItem();
        $block = $item->getBlock();
        if(!$block instanceof Crops)
            return;

        $event->cancel();
        $world = $player->getWorld();
        $pos = $event->getBlock()->getPos()->add(0.5, 1, 0.5);

        $ev = new BatchFarmingStartEvent($player, $item, $this->maxStep, $this->risePerStep, $this->clockwise);
        $ev->call();
        if($ev->isCancelled())
            return;

        $baseDirection = $player->getHorizontalFacing();
        $direction = $baseDirection;
        $add = new Vector3(0, 0, 0);
        $range = 1;
        $hasFiniteResources = $player->hasFiniteResources();
        $seeds = [];
        for($step = 0; $step < $ev->getMaxStep(); ++$step){
            if($hasFiniteResources){
                if($item->isNull()){
                    break;
                }else{
                    $item->pop();
                }
            }
            $seeds[] = new SeedObject($pos->add($add->x, $step * $ev->getRisePerStep(), $add->z), $block);

            $next = $add->getSide($direction);
            if(abs($next->x) <= $range && abs($next->z) <= $range){
                $add = $next;
            }else{
                $direction = Facing::rotateY($direction, $ev->isClockwise());
                if($direction === $baseDirection){
                    $range++;
                }
                $add = $add->getSide($direction);
            }
        }
        $player->getInventory()->setItemInHand($item);
        $this->getScheduler()->scheduleRepeatingTask(new SeedingTask($player, (int) $pos->y, $world, $seeds), 1);
    }

    private function getConfigFloat(string $k, float $default) : float{
        return (float) $this->getConfig()->get($k, $default);
    }

    private function getConfigBool(string $k, bool $default) : bool{
        $value = $this->getConfig()->get($k, $default);

        if(is_bool($value))
            return $value;

        switch(strtolower($value)){
            case "on":
            case "true":
            case "1":
            case "yes":
                return true;
        }

        return false;
    }
}