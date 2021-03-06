<?php
/**
 * This file is part of the jiratime.
 * Created by trimechmehdi.
 * Date: 5/21/18
 * Time: 13:37
 * @author: Trimech Mehdi <trimechmehdi11@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\JiraBundle\Services;

use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class JiraService
 * @package App\JiraBundle\Services
 */
class JiraService
{
    /** @var object|\Symfony\Component\HttpFoundation\RequestStack $requestStack */
    private $requestStack;
    /** @var mixed $server */
    private $server;
    /** @var Session $session */
    private $session = null;

    /** @var User */
    private $user;

    /**
     * JiraService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->requestStack = $container->get('request_stack');
        $this->server = $container->getParameter('jira_server');
    }

    /**
     * @return resource
     * @throws \Exception
     */
    private function initCurl()
    {
        if ($request = $this->requestStack->getCurrentRequest()) {
            $this->session = $request->getSession();
        }

        if (!$this->session || !$this->session->get('user')) {
            throw new \Exception('You must loggin first!');
        }

        /** @var User $user */
        if (null === $this->user) {
            $this->user = $this->session->get('user');
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERPWD, $this->user->getUsername() . ":" . $this->user->getPassword());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        return $curl;
    }

    /**
     * @return mixed
     */
    public function projects()
    {
        $curl = $this->initCurl();
        $link = "https://$this->server/rest/api/2/project";


        return $this->caching($link, $curl);
    }

    /**
     * @param string $assignee
     * @param string $project
     * @param string $toDate
     * @param string $fromDate
     * @return array
     */
    public function workLog(string $assignee = null, string $project, string $toDate, string $fromDate)
    {
        $dateStartF = str_replace('-', '%2F', $fromDate);
        $dateEndF = str_replace('-', '%2F', $toDate);
        $jql = "jql=project%20%3D%20" . $project . "%20and%20worklogDate%20%3E%3D%20'" . $dateStartF . "'%20and%20worklogDate%20%3C%3D%20'" . $dateEndF . "'%20and%20timespent%20%3E%200";

        if ($assignee) {
            $jql .= '%20and%20worklogAuthor=' . $assignee;
        }
        $curl = $this->initCurl();
        $link = "https://$this->server/rest/api/2/search?" . $jql . '&fields=key,worklog';

        $issues = $this->caching($link, $curl);

        /** @var array $data */
        $data = ['worklog' => [], 'users' => []];
        if (isset($issues['issues'])) {
            /** @var array $issue */
            foreach ($issues['issues'] as $issue) {
                $key = $issue['key'];
                $curl = $this->initCurl();

                $workLogs = $this->caching("https://$this->server/rest/api/2/issue/$key/worklog", $curl);
                if ($workLogs) {
                    foreach ($workLogs['worklogs'] as $entry) {
                        if ($assignee && $assignee === $entry['author']['key'] || !$assignee) {
                            $shortDate = substr($entry['started'], 0, 10);
                            $time = substr($entry['started'], 11, 5);
                            $data['users'][$entry['author']['name']][$shortDate] = 0;
                            if ($shortDate >= $fromDate && $shortDate <= $toDate) {
                                $data['worklog'][$key][$shortDate][$time] = $entry;

                                $data['users'][$entry['author']['name']][$shortDate] += $entry['timeSpentSeconds'];
                            }
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param $username
     * @param $password
     * @return bool
     */
    public function authenticate($username, $password) : bool
    {
        $url = 'https://' . $this->server . '/rest/auth/1/session';
        $ch = curl_init($url);
        $jsonData = array('username' => $username, 'password' => $password);
        $jsonDataEncoded = json_encode($jsonData);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $result = $this->caching($url, $ch);
        if ($result) {
            if (!isset($result['errorMessages'][0])) {
                setcookie($result['session']['name'], $result['session']['value'], time() + (86400 * 30), "/");
                return true;
            }
        }
        return false;
    }

    /**
     * @param $url
     * @param $curl
     * @return array
     */
    private function caching($url, $curl) : array
    {
        $md5 = md5($url);
        $cache = new FilesystemAdapter('', 0, __DIR__ . '/../../../../var/ws');
        $item = $cache->getItem($md5);

        if (!$item->isHit()) {
            $item->expiresAfter(\DateInterval::createFromDateString('10 minutes'));
            curl_setopt($curl, CURLOPT_URL, $url);
            $result = (array)json_decode(curl_exec($curl), true);
            curl_close($curl);

            $item->set($result);
            $cache->save($item);
        }
        return $item->get();
    }
}