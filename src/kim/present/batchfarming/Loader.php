<?php
declare(strict_types=1);

namespace kim\present\batchfarming;

use kim\present\batchfarming\entity\TargetingFallingBlock;
use kim\present\batchfarming\entity\TargetingFallingItem;
use kim\present\batchfarming\event\BatchFarmingStartEvent;
use pocketmine\block\Crops;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Fertilizer;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;

final class Loader extends PluginBase implements Listener{
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
        if(!$player->isSneaking() || TargetingFallingBlock::getCount($player) > 0)
            return;

        $item = $event->getItem();
        $block = $item->getBlock();
        if(!$item instanceof Fertilizer && !$block instanceof Crops)
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
        for($step = 0; $step < $ev->getMaxStep(); ++$step){
            if($hasFiniteResources){
                if($item->isNull()){
                    break;
                }else{
                    $item->pop();
                }
            }
            $location = Location::fromObject($pos->add($add->x, $step * $ev->getRisePerStep(), $add->z), $world);
            if($item instanceof Fertilizer){
                $entity = new TargetingFallingItem($location, $player, $item, (int) $pos->y);
            }elseif($block instanceof Crops){
                $entity = new TargetingFallingBlock($location, $player, $block, (int) $pos->y);
            }else{
                return;
            }
            $entity->spawnToAll();

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
        if($hasFiniteResources){
            $player->getInventory()->setItemInHand($item);
        }
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