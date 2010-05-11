<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * application actions.
 *
 * @package    opOpenSocialPlugin
 * @subpackage action
 * @author     Shogo Kawahara <kawahara@bucyou.net>
 */
class applicationActions extends opOpenSocialApplicationActions
{
  public function preExecute()
  {
    $this->forward404Unless(Doctrine::getTable('SnsConfig')->get('opensocial_is_enable_mobile', false));
    parent::preExecute();
    if (isset($this->application))
    {
      $this->forward404Unless($this->application->getIsMobile());
    }
  }

 /**
  * Executes list action
  *
  * @param sfWebRequest $request
  */
  public function executeList(sfWebRequest $request)
  {
    $this->pager = Doctrine::getTable('MemberApplication')->getMemberApplicationListPager(
      $request->getParameter('page', 1), 10, null, null, true, null, true
    );
  }

 /**
  * Executes gallery action
  *
  * @param sfWebRequest $request
  */
  public function executeGallery(sfWebRequest $request)
  {
    $this->searchForm = new ApplicationSearchForm();
    $this->searchForm->bind($request->getParameter('application'));
    if ($this->searchForm->isValid())
    {
      $this->pager = $this->searchForm->getPager($request->getParameter('page', 1), 10, true, null, true);
    }
  }

 /**
  * Executes add action
  *
  * @param sfWebRequest $request
  */
  public function executeAdd(sfWebRequest $request)
  {
    $memberApplication = $this->processAdd($request);
    if ($memberApplication instanceof MemberApplication)
    {
      $this->redirect('@application_render?id='.$this->application->getId());
    }
  }

 /**
  * Executes info action
  *
  * @param sfWebRequest $request
  */
  public function executeInfo(sfWebRequest $request)
  {
    $this->memberApplication =
      Doctrine::getTable('MemberApplication')->findOneByApplicationAndMember(
        $this->application, $this->getUser()->getMember()
      );
  }

 /**
  * Executes invite action
  *
  * @param sfWebRequest $request
  */
  public function executeInvite(sfWebRequest $request)
  {
    if ($request->isMethod(sfWebRequest::POST))
    {
      $request->checkCSRFProtection();
      if ($request->hasParameter('invite'))
      {
        $result = $this->processInvite($request);
        $callback = '@application_render?id='.$this->application->getId();
        if ($request->hasParameter('callback'))
        {
          $callback .= '&url='.urlencode($request->getParameter('callback'));
        }
        $this->redirect($callback);
      }
    }

    $fromMember = $this->getUser()->getMember();
    $this->nowpage = (int)$request->getParameter('nowpage', 1);
    if ($request->hasParameter('previous'))
    {
      $this->nowpage--;
    }
    else if ($request->hasParameter('next'))
    {
      $this->nowpage++;
    }

    $this->ids = $request->getParameter('ids', array());

    $this->forward404Unless($this->application->isHadByMember($fromMember->getId()));
    $this->pager = Doctrine::getTable('MemberRelationship')->getFriendListPager($fromMember->getId(), $this->nowpage, 15);
    $this->installedFriends = Doctrine::getTable('MemberApplication')->getInstalledFriendIds($this->application, $fromMember);
    $this->form = new BaseForm();
  }

 /**
  * Executes render action
  *
  * @param sfWebRequest $request
  */
  public function executeRender(sfWebRequest $request)
  {
    include_once sfConfig::get('sf_lib_dir').'/vendor/OAuth/OAuth.php';

    $this->memberApplication = Doctrine::getTable('MemberApplication')
      ->findOneByApplicationAndMember($this->application, $this->member);
    $this->forward404Unless($this->memberApplication);

    $views = $this->application->getViews();
    $this->forward404Unless(
      isset($views['mobile']) &&
      isset($views['mobile']['type']) &&
      isset($views['mobile']['href']) &&
      'URL' === strtoupper($views['mobile']['type'])
    );
    $url = $request->getParameter('url', $views['mobile']['href']);
    $zendUri = Zend_Uri_Http::fromString($url);
    $queryString = $zendUri->getQuery();
    $zendUri->setQuery('');
    $zendUri->setFragment('');
    $url = $zendUri->getUri();
    parse_str($queryString, $query);

    $params = array(
      'opensocial_app_id'    => $this->application->getId(),
      'opensocial_owner_id'  => $this->member->getId()
    );
    $params = array_merge($query, $params);
    $method = $request->isMethod(sfWebRequest::POST) ? 'POST' : 'GET';

    $consumer = new OAuthConsumer(opOpenSocialToolKit::getOAuthConsumerKey(), null, null);
    $signatureMethod = new OAuthSignatureMethod_RSA_SHA1_opOpenSocialPlugin();
    $httpOptions = opOpenSocialToolKit::getHttpOptions();
    $oauthRequest = OAuthRequest::from_consumer_and_token($consumer, null, $method, $url, $params);
    $oauthRequest->sign_request($signatureMethod, $consumer, null);

    $client = new Zend_Http_Client();
    if ('POST' !== $method)
    {
      $client->setMethod(Zend_Http_Client::GET);
      $url .= '?'.OAuthUtil::build_http_query($params);
    }
    else
    {
      $params = array_merge($params, $request->getPostParameters());
      $client->setMethod(Zend_Http_Client::POST);
      $client->setHeaders(Zend_Http_Client::CONTENT_TYPE, Zend_Http_Client::ENC_URLENCODED);
      $client->setRawData(OAuthUtil::build_http_query($params));
    }
    $client->setConfig($httpOptions);
    $client->setUri($url);
    $client->setHeaders($oauthRequest->to_header());
    $client->setHeaders(opOpenSocialToolKit::getProxyHeaders($request, sfConfig::get('op_opensocial_is_strip_uid', true)));

    $response = $client->request();
    if ($response->isSuccessful())
    {
      $contentType = $response->getHeader('Content-Type');

      if (preg_match('#^(text/html|application/xhtml\+xml|application/xml|text/xml)#', $contentType, $match))
      {
        header('Content-Type: '.$match[0].'; charset=Shift_JIS');
        echo opOpenSocialToolKit::rewriteBodyForMobile($this, $response->getBody());
        exit;
      }
      else
      {
        header('Content-Type: '.$response->getHeader('Content-Type'));
        echo $response->getBody();
        exit;
      }
    }
    return sfView::ERROR;
  }
}