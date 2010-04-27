<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * PluginApplicationInviteTable
 *
 * @package    opOpenSocialPlugin
 * @subpackage model
 * @author     Shogo Kawahara <kawahara@tejimaya.net>
 */
class PluginApplicationInviteTable extends Doctrine_Table
{
  public function getInvitesByToMemberId($memberId = null, $isPc = null, $isMobile = null)
  {
    if (null === $memberId)
    {
      $memberId = sfContext::getInstance()->getUser()->getMemberId();
    }

    $query = $this->createQuery('ap')
      ->where('ap.to_member_id = ?', $memberId);

    if (null !== $isPc || null !== $isMobile)
    {
      $query->innerJoin('ap.Application a');
    }

    if (null !== $isPc)
    {
      $quer->andWhere('a.is_pc = ?', $isPc);
    }

    if (null !== $isMobile)
    {
      $query->andWhere('a.is_mobile = ?', $isMobile);
    }

    return $query->execute();
  }

  public function inviteApplicationList(sfEvent $event)
  {
    $isPc = ('pc_frontend' === sfConfig::get('sf_app')) ? true : null;
    $isMobile = ('mobile_frontend' === sfConfig::get('sf_app')) ? true : null;

    $invites = Doctrine::getTable('ApplicationInvite')->getInvitesByToMemberId($event['member']->id, $isPc, $isMobile);
    $list = array();
    foreach ($invites as $invite)
    {
      $application = $invite->getApplication();
      $fromMember = $invite->getFromMember();
      $list[] = array(
        'id' => $invite->getId(),
        'image' => array(
          'url' => $fromMember->getImageFileName(),
          'link' => '@obj_member_profile?id='.$fromMember->getId(),
        ),
        'list' => array(
          'App name' => array(
            'text' => $application->getTitle(),
            'link' => '@application_info?id='.$application->getId()
          ),
          'Member who invited this' => array(
            'text' => $fromMember->getName(),
            'link' => '@obj_member_profile?id='.$fromMember->getId()
          )
        ),
      );
    }

    $event->setReturnValue($list);

    return true;
  }

  public function processApplicationConfirm(sfEvent $event)
  {
    $app = sfConfig::get('sf_app');
    $invite = Doctrine::getTable('ApplicationInvite')->find($event['id']);

    if (!$invite)
    {
      return false;
    }

    $application = $invite->getApplication();
    if ('pc_frontend' === $app)
    {
      if (!$application->getIsPc())
      {
        return false;
      }
    }
    elseif ('mobile_frontend' === $app)
    {
      if (!$application->getIsMobile())
      {
        return false;
      }
    }

    if ($event['is_accepted'])
    {
      $memberApplication = $invite->accept();
      $invite->delete();
      $action = $event->getSubject();
      if ($action instanceof sfAction)
      {
        if ('pc_frontend' === $app)
        {
          $action->redirect('@application_canvas?id='.$memberApplication->getId());
        }
        elseif ('mobile_frontend' === $app)
        {
          $action->redirect('@application_render?id='.$memberApplication->getApplicationId());
        }
      }
    }
    else
    {
      $invite->delete();
      $event->setReturnValue("You have just rejected request of invitation to app.");
    }

    return true;
  }
}