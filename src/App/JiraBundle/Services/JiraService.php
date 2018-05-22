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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\User\User;

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

        curl_setopt($curl, CURLOPT_URL, $link);
        $projects = json_decode(curl_exec($curl), true);

        return $projects;
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
        $curl = $this->initCurl();
        $link = "https://$this->server/rest/api/2/search?startIndex=0&jql=";
        if ($assignee) {
            $link .= 'assignee+%3D+' . $assignee . '+and+';
        }
        $link .= "project+%3D+$project+and+created+%3C+$toDate+and+updated+%3E+$fromDate+" .
            "and+timespent+%3E+0&fields=key";

        curl_setopt($curl, CURLOPT_URL, $link);

        $issues = json_decode(curl_exec($curl), true);
        $periodLog = [];
        if ($issues) {
            foreach ($issues['issues'] as $issue) {
                $key = $issue['key'];
                curl_setopt($curl, CURLOPT_URL,
                    "https://$this->server/rest/api/2/issue/$key/worklog");

                $workLogs = json_decode(curl_exec($curl), true);
                if ($workLogs) {
                    foreach ($workLogs['worklogs'] as $entry) {
                        $shortDate = substr($entry['started'], 0, 10);
                        $time = substr($entry['started'], 11, 8);
                        if ($shortDate >= $fromDate && $shortDate <= $toDate)
                            $periodLog[$key][$shortDate][$time] = $entry;
                    }
                }
            }
        }

        return $periodLog;
    }

    /**
     * @param $username
     * @param $password
     * @return bool
     */
    public function authenticate($username, $password) : bool
    {
        $ch = curl_init('https://' . $this->server . '/rest/auth/1/session');
        $jsonData = array('username' => $username, 'password' => $password);
        $jsonDataEncoded = json_encode($jsonData);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);
        if ($result) {
            if (!isset($result['errorMessages'][0])) {
                setcookie($result['session']['name'], $result['session']['value'], time() + (86400 * 30), "/");
                return true;
            }
        }
        return false;
    }
}