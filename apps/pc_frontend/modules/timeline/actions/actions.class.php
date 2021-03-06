<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * timeline actions.
 *
 * @package    OpenPNE
 * @subpackage timeline
 * @author     Shouta Kashiwagi <kashiwagi@tejimaya.com>
 * @version    SVN: $Id: actions.class.php 9301 2008-05-27 01:08:46Z dwhittle $
 */

class timelineActions extends sfActions
{
 /**
  * Executes index action
  *
  * @param sfWebRequest $request A request object
  */
  public function executeIndex(sfWebRequest $request)
  {
    if ($this->isSmt())
    {
      return $this->executeSmtIndex($request);
    }

    $this->baseUrl = sfConfig::get('op_base_url');
    $form = new sfForm();
    $this->token = $form->getCSRFToken();
    return sfView::SUCCESS;
  }

  public function executeSmtIndex(sfWebRequest $request)
  {
    $this->setLayout('smtLayoutHome');
    $this->baseUrl = sfConfig::get('op_base_url');
    $form = new sfForm();
    $this->token = $form->getCSRFToken();
    return sfView::SUCCESS;
  }

  public function executeMember(sfWebRequest $request)
  {
    if ($this->isSmt())
    {
      return $this->executeSmtMember($request);
    }
    $this->memberId = $request->getParameter('id', $this->getUser()->getMember()->getName());
    $this->baseUrl = sfConfig::get('op_base_url');
    $form = new sfForm();
    $this->token = $form->getCSRFToken();
    return sfView::SUCCESS;
  }

  public function executeSmtMember($request)
  {
    $this->memberId = (int)$request->getParameter('id', $this->getUser()->getMember()->getName());
    $this->member = Doctrine::getTable('Member')->find($this->memberId);
    $this->baseUrl = sfConfig::get('op_base_url');
    $form = new sfForm();
    $this->token = $form->getCSRFToken();
    $this->setLayout('smtLayoutMember');
    $this->getResponse()->setDisplayMember($this->member);  
    $this->setTemplate('smtMember');
    return sfVIew::SUCCESS;
  }

  public function executeMentions(sfWebRequest $request)
  {
    $this->baseUrl = sfConfig::get('op_base_url');
    $form = new sfForm();
    $this->token = $form->getCSRFToken();
    return sfView::SUCCESS;
  }

  public function executeGet(sfWebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'I18N', 'sfImage', 'Url', 'Tag', 'opUtil','Escaping', 'opTimeline'));
    $ac = array();
    $activityIds = array();
    $mode = $request->getParameter('mode');
    $list = $request->getParameter('list');
    $memberId = (int) $request->getParameter('memberId');
    $lastId = (int) $request->getParameter('lastId'); 
    $moreId = (int) $request->getParameter('moreId');
    $limit = (int) $request->getParameter('limit', 20);
    $communityId = (int) $request->getParameter('communityId');
    $activityData = Doctrine_Query::create()->from('ActivityData ad');

    switch ($list)
    {
      case 'all':
        $activityData = $activityData->where('ad.in_reply_to_activity_id IS NULL');
        break;

      case 'more':
        if (!is_numeric($moreId))
        {
          $this->jsonError('Request parameter \'count\' must be numeric.');
          exit;
        }
        $activityData = $activityData->where('ad.id < ?', $moreId)
                                     ->andWhere('ad.in_reply_to_activity_id IS NULL');
        break;

      case 'check':
        if (!is_numeric($lastId))
        {
          $this->jsonError('Request parameter \'lastId\' must be numeric.');
          exit;
        }
        $activityData = $activityData->where('ad.in_reply_to_activity_id IS NULL')
                                     ->andWhere('ad.id > ?', $lastId);
        break;
      default:
        $activityData = $activityData->where('ad.in_reply_to_activity_id IS NULL');
    }

    switch ($mode)
    {
      case 'member':
        if (!is_numeric($memberId))
        {
          $this->jsonError('Request parameter \'memberId\' must be numeric.');
          exit;
        }
        $activityData = $activityData->andWhere('ad.member_id = ?', $memberId)
                                     ->andWhere('ad.foreign_table IS NULL')
                                     ->andWhere('ad.foreign_id IS NULL')
                                     ->andWhere('ad.public_flag = ?', 1)
                                     ->orderBy('ad.id DESC');
        break;
      case 'community':
        if (!is_numeric($communityId))
        {
          $this->jsonError('Request parameter \'communityId\' must be numeric.'); 
        }
        $activityData = $activityData->andWhere('ad.foreign_table = ?', 'community')
                                     ->andWhere('ad.foreign_id = ?', $communityId)
                                     ->orderBy('ad.id DESC');
        break;
      default:
        $activityData = $activityData->andWhere('ad.foreign_table IS NULL')
                                     ->andWhere('ad.foreign_id IS NULL')
                                     ->andWhere('ad.public_flag = ?', 1);
        $activityData = $activityData->orderBy('ad.id DESC');
    }
    $activityData = $activityData->limit($limit);
    $activityData = $activityData->execute();

    foreach ($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = url_for('@homepage') . 'images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), url_for('@homepage')));
      $body = op_timeline_plugin_body_filter($activity, $body);
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();
  
      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'inline';
      }
      else
      {
        $deleteLink = 'none';
      }
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)),
      ); 
      $activityIds[] = $id;
    }

    $count = count($ac);
    $i = 0;

    $commentData = Doctrine_Query::create()->from('ActivityData ad')->whereIn('ad.in_reply_to_activity_id', $activityIds)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->execute();
    foreach ($commentData as $activity)
    {
      $inReplyToActivityId = $activity->getInReplyToActivityId();
      for ($j=0;$j<$count;$j++)
      {
        if ($ac[$j]['id']==$inReplyToActivityId)
        {
          $member = Doctrine::getTable('Member')->find($activity->getMemberId());
          $cm = array();
          $cm['id'] = $activity->getId();
          $cm['memberId'] = $member->getId();
          $cm['memberName'] = $member->getName();
          if (!$member->getImageFileName())
          {
            $cm['memberImage'] = url_for('@homepage') . 'images/no_image.gif';
          }
          else
          {
            $memberImageFile = $member->getImageFileName();
            $cm['memberImage'] = sf_image_path($memberImageFile, array('size' => '48x48',));
          }
          $cm['memberScreenName'] = $this->getScreenName($cm['memberId']) ? $this->getScreenName($cm['memberId']) : $cm['memberName'];
          $cm['body'] = opTimelinePluginUtil::screenNameReplace(sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), $activity->getBody()), $baseUrl);
          if ($cm['memberId']==$this->getUser()->getMember()->getId())
          {
            $cm['deleteLink'] = 'inline';
          }
          else
          {
            $cm['deleteLink'] = 'none';
          }
          $cm['uri'] = $activity->getUri();
          $cm['source'] = $activity->getSource();
          $cm['sourceUri'] = $activity->getSourceUri();
          $cm['createdAt'] = op_format_activity_time(strtotime($activity->getCreatedAt()));
          $ac[$j]['reply'][] = $cm;
        }
      }
      $i++;
    }
    $json = array( 'status' => 'success', 'data' => $ac, );
 
    return $this->renderText(json_encode($json));
  }

  private function jsonError($message)
  {
    $json = array('status' => 'error', 'message' => $message);
    return $this->renderText(json_encode($json));
  }

  public function executeList(sfWebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'I18N', 'sfImage', 'Url', 'Tag', 'opUtil','Escaping', 'opTimeline'));
    $baseUrl = sfConfig::get('op_base_url');
    $ac = array();
    $activityIds = array();
    $activityData = Doctrine_Query::create()->from('ActivityData ad')->where('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->orderBy('ad.id DESC')->limit(20)->execute();
    foreach ($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = $baseUrl . '/images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
      $body = op_timeline_plugin_body_filter($activity, $body);
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();
  
      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'inline';
      }
      else
      {
        $deleteLink = 'none';
      }
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)), 
        'baseUrl' => sfConfig::get('op_base_url'),
      ); 
      $activityIds[] = $id;
    }

    $count = count($ac);
    $i = 0;
    $commentData = Doctrine_Query::create()->from('ActivityData ad')->whereIn('ad.in_reply_to_activity_id', $activityIds)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->execute();
    foreach ($commentData as $activity)
    {
      $inReplyToActivityId = $activity->getInReplyToActivityId();
      for ($j=0;$j<$count;$j++)
      {
        if ($ac[$j]['id']==$inReplyToActivityId)
        {
          $member = Doctrine::getTable('Member')->find($activity->getMemberId());
          $cm = array();
          $cm['id'] = $activity->getId();
          $cm['memberId'] = $member->getId();
          $cm['memberName'] = $member->getName();
          if (!$member->getImageFileName())
          {
            $cm['memberImage'] = $baseUrl . '/images/no_image.gif';
          }
          else
          {
            $memberImageFile = $member->getImageFileName();
            $cm['memberImage'] = sf_image_path($memberImageFile, array('size' => '48x48',));
          }
          $cm['memberScreenName'] = $this->getScreenName($cm['memberId']) ? $this->getScreenName($cm['memberId']) : $cm['memberName'];
          // $cm['body'] = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
          $cm['body'] = opTimelinePluginUtil::screenNameReplace(sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), $activity->getBody()), $baseUrl);
          if ($cm['memberId']==$this->getUser()->getMember()->getId())
          {
            $cm['deleteLink'] = 'inline';
          }
          else
          {
            $cm['deleteLink'] = 'none';
          }
          $cm['uri'] = $activity->getUri();
          $cm['source'] = $activity->getSource();
          $cm['sourceUri'] = $activity->getSourceUri();
          $cm['createdAt'] = op_format_activity_time(strtotime($activity->getCreatedAt()));
          $cm['baseUrl'] = sfConfig::get('op_base_url');
          $ac[$j]['reply'][] = $cm;
        }
      }
      $i++;
    }
    $json = array( 'status' => 'success', 'data' => $ac, );
 
    return $this->renderText(json_encode($json));
  }

  public function executeListMore(sfwebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'sfImage', 'opUtil','Escaping'));
    $lastId = $request->getParameter('id');
    $baseUrl = sfConfig::get('op_base_url');
    if (is_null($lastId) && !is_numeric($lastId))
    {
      $json = array('status' => 'error', 'message' => 'Your request last-id is INVALID.');
      return $this->renderText(json_encode($json));
    }
    if ($request->getParameter('count') && is_numeric($request->getParameter('count')))
    {
      $count = $request->getParameter('count');
    }
    else
    {
      $count = 20;
    }
    $ac = array();
    $activityIds = array();
    $activityData = Doctrine_Query::create()->from('ActivityData ad')->where('ad.id < ?', $lastId)->andWhere('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->orderBy('ad.id DESC')->limit($count)->execute();
    foreach ($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = $baseUrl . '/images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();
  
      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'inline';
      }
      else
      {
        $deleteLink = 'none';
      }
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)), 
        'baseUrl' => sfConfig::get('op_base_url'),
      ); 
      $activityIds[] = $id;
    }

    $count = count($ac);
    $i = 0;
    $commentData = Doctrine_Query::create()->from('ActivityData ad')->whereIn('ad.in_reply_to_activity_id', $activityIds)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->execute();
    foreach ($commentData as $activity)
    {
      $inReplyToActivityId = $activity->getInReplyToActivityId();
      for ($j=0;$j<$count;$j++)
      {
        if ($ac[$j]['id']==$inReplyToActivityId)
        {
          $member = Doctrine::getTable('Member')->find($activity->getMemberId());
          $cm = array();
          $cm['id'] = $activity->getId();
          $cm['memberId'] = $member->getId();
          $cm['memberName'] = $member->getName();
          if (!$member->getImageFileName())
          {
            $cm['memberImage'] = $baseUrl . '/images/no_image.gif';
          }
          else
          {
            $memberImageFile = $member->getImageFileName();
            $cm['memberImage'] = sf_image_path($memberImageFile, array('size' => '48x48',));
          }
          $cm['memberScreenName'] = $this->getScreenName($cm['memberId']) ? $this->getScreenName($cm['memberId']) : $cm['memberName'];
          $cm['body'] = opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl);
          if ($cm['memberId']==$this->getUser()->getMember()->getId())
          {
            $cm['deleteLink'] = 'inline';
          }
          else
          {
            $cm['deleteLink'] = 'none';
          }
          $cm['uri'] = $activity->getUri();
          $cm['source'] = $activity->getSource();
          $cm['sourceUri'] = $activity->getSourceUri();
          $cm['createdAt'] = op_format_activity_time(strtotime($activity->getCreatedAt()));
          $cm['baseUrl'] = sfConfig::get('op_base_url');
          $ac[$j]['reply'][] = $cm;
        }
      }
      $i++;
    }
    $json = array('status' => 'success', 'data' => $ac);
    return $this->renderText(json_encode($json));

  }

  public function executeListDifference(sfWebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'sfImage', 'opUtil','Escaping'));
    $lastId = $request->getParameter('id');
    if (is_null($lastId) && !is_numeric($lastId))
    {
      $json = array('status' => 'error', 'message' => 'Your request last-id is INVALID.');
      return $this->renderText(json_encode($json));
    }
    $ac = array();
    $activityIds = array();
    $activityData = Doctrine_Query::create()->from('ActivityData ad')->where('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.id > ?', $lastId)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->orderBy('ad.id DESC')->execute();
    foreach ($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = $baseUrl . '/images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();
  
      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'inline';
      }
      else
      {
        $deleteLink = 'none';
      }
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)), 
        'baseUrl' => sfConfig::get('op_base_url'),
      ); 
      $activityIds[] = $id;
    }

    $count = count($ac);
    $i = 0;
    $commentData = Doctrine_Query::create()->from('ActivityData ad')->whereIn('ad.in_reply_to_activity_id', $activityIds)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->execute();
    foreach ($commentData as $activity)
    {
      $inReplyToActivityId = $activity->getInReplyToActivityId();
      for ($j=0;$j<$count;$j++)
      {
        if ($ac[$j]['id']==$inReplyToActivityId)
        {
          $member = Doctrine::getTable('Member')->find($activity->getMemberId());
          $cm = array();
          $cm['id'] = $activity->getId();
          $cm['memberId'] = $member->getId();
          $cm['memberName'] = $member->getName();
          if (!$member->getImageFileName())
          {
            $cm['memberImage'] = $baseUrl . '/images/no_image.gif';
          }
          else
          {
            $memberImageFile = $member->getImageFileName();
            $cm['memberImage'] = sf_image_path($memberImageFile, array('size' => '48x48',));
          }
          $cm['memberScreenName'] = $this->getScreenName($cm['memberId']) ? $this->getScreenName($cm['memberId']) : $cm['memberName'];
          $cm['body'] = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
          if ($cm['memberId']==$this->getUser()->getMember()->getId())
          {
            $cm['deleteLink'] = 'inline';
          }
          else
          {
            $cm['deleteLink'] = 'none';
          }
          $cm['uri'] = $activity->getUri();
          $cm['source'] = $activity->getSource();
          $cm['sourceUri'] = $activity->getSourceUri();
          $cm['createdAt'] = op_format_activity_time(strtotime($activity->getCreatedAt()));
          $cm['baseUrl'] = sfConfig::get('op_base_url');
          $ac[$j]['reply'][] = $cm;
        }
      }
      $i++;
    }
    $json = array( 'status' => 'success', 'data' => $ac, );
    return $this->renderText(json_encode($json));
  }


  public function executeListMember(sfWebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'sfImage', 'opUtil','Escaping'));
    $baseUrl = sfConfig::get('op_base_url');
    $memberId = $request->getParameter('id');
    if (is_null($memberId) || !is_numeric($memberId))
    {
      $json = array('status' => 'error', 'message' => 'Error. your request member id is invalid.');
      return $this->renderText(json_encode($json));
    }
    $ac = array();
    $activityIds = array();
    $activityData = Doctrine_Query::create()->from('ActivityData ad')->where('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.member_id = ?', $memberId)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->orderBy('ad.id DESC')->limit(20)->execute();
    foreach ($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = $baseUrl . '/images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();
  
      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'inline';
      }
      else
      {
        $deleteLink = 'none';
      }
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)), 
        'baseUrl' => sfConfig::get('op_base_url'),
      ); 
      $activityIds[] = $id;
    }

    $count = count($ac);
    $i = 0;
    $commentData = Doctrine_Query::create()->from('ActivityData ad')->whereIn('ad.in_reply_to_activity_id', $activityIds)->andWhere('ad.foreign_table IS NULL')->andWhere('ad.foreign_id IS NULL')->andWhere('ad.public_flag = ?', 1)->execute();
    foreach ($commentData as $activity)
    {
      $inReplyToActivityId = $activity->getInReplyToActivityId();
      for ($j=0;$j<$count;$j++)
      {
        if ($ac[$j]['id']==$inReplyToActivityId)
        {
          $member = Doctrine::getTable('Member')->find($activity->getMemberId());
          $cm = array();
          $cm['id'] = $activity->getId();
          $cm['memberId'] = $member->getId();
          $cm['memberName'] = $member->getName();
          if (!$member->getImageFileName())
          {
            $cm['memberImage'] = $baseUrl . '/images/no_image.gif';
          }
          else
          {
            $memberImageFile = $member->getImageFileName();
            $cm['memberImage'] = sf_image_path($memberImageFile, array('size' => '48x48',));
          }
          $cm['memberScreenName'] = $this->getScreenName($cm['memberId']) ? $this->getScreenName($cm['memberId']) : $cm['memberName'];
          $cm['body'] = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
          if ($cm['memberId']==$this->getUser()->getMember()->getId())
          {
            $cm['deleteLink'] = 'inline';
          }
          else
          {
            $cm['deleteLink'] = 'none';
          }
          $cm['uri'] = $activity->getUri();
          $cm['source'] = $activity->getSource();
          $cm['sourceUri'] = $activity->getSourceUri();
          $cm['createdAt'] = op_format_activity_time(strtotime($activity->getCreatedAt()));
          $cm['baseUrl'] = sfConfig::get('op_base_url');
          $ac[$j]['reply'][] = $cm;
        }
      }
      $i++;
    }
    $json = array( 'status' => 'success', 'data' => $ac, );
    return $this->renderText(json_encode($json));
  }

  public function executeListCommunity(sfWebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'sfImage', 'opUtil','Escaping'));
    $baseUrl = sfConfig::get('op_base_url');
    $cid = $request->getParameter('id');
    if (!is_numeric($cid))
    {
      $json = array('status' => 'error', 'message' => 'your request id is not numeric.',);
      return $this->renderText(json_encode($json));
    }
    $ac = array();
    $activityIds = array();
    $activityData = Doctrine_Query::create()->from('ActivityData ad')->where('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.public_flag = ?', 1)->andWhere('ad.foreign_table = ?', 'community')->andWhere('ad.foreign_id = ?', $cid)->orderBy('ad.id DESC')->limit(20)->execute();
    foreach ($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = $baseUrl . '/images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();

      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'show';
      }
      else
      {
        $deleteLink = 'none';
      }
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)), 
        'baseUrl' => sfConfig::get('op_base_url'),
      );
      $activityIds[] = $id;
    }
    $count = count($ac);
    $i = 0;
    $commentData = Doctrine_Query::create()->from('ActivityData ad')->whereIn('ad.in_reply_to_activity_id', $activityIds)->andWhere('ad.foreign_table = ?', 'community')->andWhere('ad.foreign_id = ?', $cid)->andWhere('ad.public_flag = ?', 1)->execute();
    foreach ($commentData as $activity)
    {
      $inReplyToActivityId = $activity->getInReplyToActivityId();
      for ($j=0;$j<$count;$j++)
      {
        if ($ac[$j]['id']==$inReplyToActivityId)
        { 
          $member = Doctrine::getTable('Member')->find($activity->getMemberId());
          if (!$member->getImageFileName())
          {
            $memberImage = $baseUrl . '/images/no_image.gif';
          }
          else
          {
            $memberImageFile = $member->getImageFileName();
            $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
          }
          $cm = array();
          $cm['id'] = $activity->getId();
          $cm['memberId'] = $member->getId();
          $cm['memberName'] = $member->getName();
          $cm['memberScreenName'] = $this->getScreenName($cm['memberId']) ? $this->getScreenName($cm['memberId']) : $cm['memberName'];
          $cm['memberImage'] = $memberImage;
          $cm['body'] = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
          if ($cm['memberId']==$this->getUser()->getMember()->getId())
          {
            $cm['deleteLink'] = 'show';
          }
          else
          {
            $cm['deleteLink'] = 'none';
          }
          $cm['uri'] = $activity->getUri();
          $cm['source'] = $activity->getSource();
          $cm['sourceUri'] = $activity->getSourceUri();
          $cm['createdAt'] = op_format_activity_time(strtotime($activity->getCreatedAt()));
          $cm['baseUrl'] = sfConfig::get('op_base_url');
          $ac[$j]['reply'][] = $cm;
        }
      }
    }
    $i++;
    $json = array( 'status' => 'success', 'data' => $ac, );
    return $this->renderText(json_encode($json));
  }

  public function executeListMention(sfWebRequest $request)
  {
    sfContext::getInstance()->getConfiguration()->loadHelpers(array('Helper', 'Date', 'sfImage', 'opUtil','Escaping'));
    $baseUrl = sfConfig::get('op_base_url');
    $memberId = $this->getUser()->getMember()->getId();
    $mines = Doctrine::getTable('ActivityData')->findByMemberId($memberId);
    $replyId = array();
    foreach ($mines as $mine)
    {
      $replyId[] = $mine->getId();
    }
 
    $activityData = Doctrine_Query::create()->from('ActivityData ad')->where('ad.template = ?', 'mention_member_id')->andWhere('ad.template_param LIKE ?', '%|' . $memberId . '|%')->execute();
    foreach($activityData as $activity)
    {
      $id = $activity->getId();
      $memberId = $activity->getMemberId();
      $member = Doctrine::getTable('Member')->find($memberId);
      if (!$member->getImageFileName())
      {
        $memberImage = $baseUrl . '/images/no_image.gif';
      }
      else
      {
        $memberImageFile = $member->getImageFileName();
        $memberImage = sf_image_path($memberImageFile, array('size' => '48x48',));
      }
      $memberName = $member->getName();
      $memberScreenName = $this->getScreenName($memberId) ? $this->getScreenName($memberId) : $memberName;
      $body = sfOutputEscaper::escape(sfConfig::get('sf_escaping_method'), opTimelinePluginUtil::screenNameReplace($activity->getBody(), $baseUrl));
      $uri = $activity->getUri();
      $source = $activity->getSource();
      $sourceUri = $activity->getSourceUri();
      $createdAt = $activity->getCreatedAt();

      if ($memberId==$this->getUser()->getMember()->getId())
      {
        $deleteLink = 'show';
      }
      else
      {
        $deleteLink = 'none';
      }
      
      $ac[] = array( 
        'id' => $id, 
        'memberId' => $memberId, 
        'memberImage' => $memberImage, 
        'memberScreenName' => $memberScreenName, 
        'memberName' => $memberName,
        'body' => $body,  
        'deleteLink' => $deleteLink,
        'uri' => $uri, 
        'source' => $source, 
        'sourceUri' => $sourceUri, 
        'createdAt' => op_format_activity_time(strtotime($createdAt)), 
        'baseUrl' => sfConfig::get('op_base_url'),
      ); 
    }
    $json = array( 'status' => 'success', 'data' => $ac, );
    return $this->renderText(json_encode($json));
  }

  public function executePost(sfWebRequest $request)
  {
    $form = new sfForm(); 
    $token = $form->getCSRFToken();
    if ($token=!$request->getParameter('CSRFtoken'))
    {
      $json = array('status' => 'error', 'message' => 'Error. Invalid CSRF token Key.');
      return $this->renderText(json_encode($json));
    }
    if (!$request->getParameter('body'))
    {
      $json = array('status' => 'error', 'message' => 'Error. Body is null.',);
      return $this->renderText(json_encode($json));
    }
    $activity = new ActivityData();
    $activity->setMemberId($this->getUser()->getMemberId()); 
    $activity->setBody($request->getParameter('body'));
    $mentions = opTimelinePluginUtil::hasScreenName($request->getParameter('body'));
    if (!is_null($mentions))
    {
      $activity->setTemplate('mention_member_id');
      $activity->setTemplateParam($mentions);
    }
    $inReplyToActivityId = $request->getParameter('replyId');
    if (isset($inReplyToActivityId) && is_numeric($inReplyToActivityId))
    {
      $activity->setInReplyToActivityId($inReplyToActivityId);
    }
    $foreign = $request->getParameter('foreign');
    $foreignId = $request->getParameter('foreignId');
    if (isset($foreign) && isset($foreignId) && is_numeric($foreignId))
    {
      $activity->setForeignTable($foreign); 
      $activity->setForeignId($foreignId);
    }
    $activity->setPublicFlag(1);
    $activity->save();
    $json = array( 'status' => 'success', 'message' => 'UPDATE was succeed!', );
    return $this->renderText(json_encode($json));
  }

  public function executeAddLike(sfWebRequest $request)
  {
    /**************
    $form = new sfForm();
    $token = $form->getCSRFToken();
    if ($token!=$request->getParameter('CSRFtoken'))
    {
      $json = array('status' => 'error', 'message' => 'Error. CSRF token is invalid.');
      return $this->renderText(json_encode($json));
    }
    if(is_null($request->getParameter('id')))
    {
      $json = array('status' => 'error', 'message' => 'Error. request Id is not set.');
      return $this->renderText(json_encode($json));
    }
    $timelineLike = new TimelineLike();
    $timelineLike->setActivityDataId($request->getParameter('id'));
    $timelineLike->setMemberId($this->getUser()->getMemberId());
    $timelineLike->save();
    $json = array('status' => 'success', 'message' => 'added Like.');
    return $this->renderText(json_encode($json));
    ****************/
  }

  public function executeDelete(sfWebRequest $request)
  {
    $form = new sfForm();
    $token = $form->getCSRFToken();
    if ($token=!$request->getParameter('CSRFtoken'))
    {
      $json = array('status' => 'error', 'message' => 'Error. Invalid CSRF token key.', );
      return $this->renderText(json_encode($json));
    }
    $activityId = $request->getParameter('activityId');
    if (!isset($activityId) || !is_numeric($activityId))
    {
      $json = array( 'status' => 'error', 'message' => 'Error. Activity Id is not set.');
      return $this->renderText(json_encode($json));
    }
    $memberId = $this->getUser()->getMemberId();

    $activityData = Doctrine::getTable('ActivityData')->findByIdAndMemberId($activityId, $memberId);

    if (!$activityData)
    {
      $json = array( 'status' => 'error', 'message' => 'Error. Your Request Activity Id does not exist.',);
      return $this->renderText(json_encode($json));
    }
    $activityData->delete();
    $json = array( 'status' => 'success', 'message' => 'Your Delete Request has been succeed!' );
    return $this->renderText(json_encode($json));
  }

  public function executeJs(sfWebRequest $request)
  {
    $this->baseUrl = sfConfig::get('op_base_url');
    $this->mode = $request->getParameter('mode');
    $this->cid = $request->getParameter('cid');
    if ($this->mode==2)
    {
      $this->foreigntable = 'community';
    }
    $form = new sfForm();
    $this->csrfToken = $form->getCSRFToken();
    return sfView::SUCCESS;
  }

/************
  public function executeListDifference(sfWebRequest $request)
  {
    $lastId = $request->getRequest('lastId');
    $m = $request->getParameter('m');
    if (is_null($m))
    {
      if (!is_null($lasiId) && is_numeric($lastId))
      {
        Doctrine_Query::create()->from('ActivityData ad')->where('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.public_flag = ?', 1)->andWhere('ad.foreign_table = ?', 'community')->andWhere('ad.foreign_id' = ?)->orderBy('ad.id DESC')->limit(20)->execute();
      }
    }
    elseif($m="community")
    {
      if (!is_null($lastid) && is_numeric($lastId))
      {
        Doctrine_Query::create()->from('ActivityData ad')->where('ad.in_reply_to_activity_id IS NULL')->andWhere('ad.public_flag = ?' 1)->andWhere('ad.foreign_table IS NULL')->execute();
      }
    }
  }
***********/

  public function executeTimelinePlugin(sfWebRequest $request)
  {
    $this->baseUrl = sfConfig::get('op_base_url');
    $form = new sfForm();
    $this->csrfToken = $form->getCSRFToken();
    return sfView::SUCCESS;
  }

  private function getScreenName($memberId)
  {
    $memberConfig = Doctrine::getTable('MemberConfig')->findOneByMemberIdAndName($memberId, 'op_screen_name');
    if ($memberConfig)
    {
      return "@".$memberConfig->getValue();
    }
    else
    {
      return false;
    }
  }

  private function isSmt()
  {
    return (preg_match('/iPhone/', $_SERVER['HTTP_USER_AGENT']) || preg_match('/Android/', $_SERVER['HTTP_USER_AGENT']));
  }
}
