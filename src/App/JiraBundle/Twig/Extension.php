<?php
/**
 * This file is part of the jiratime.
 * Created by trimechmehdi.
 * Date: 5/21/18
 * Time: 14:43
 * @author: Trimech Mehdi <trimechmehdi11@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\JiraBundle\Twig;

/**
 * Class Extension
 * @package App\JiraBundle\Twig
 */
class Extension extends \Twig_Extension
{
    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('display_totals', [$this, 'displayTotals']),
        ];
    }

    /**
     * @param array $periodLog
     * @param array $dates
     * @return string
     */
    public function displayTotals(array $periodLog, array $dates) : string
    {
        $totals = [];

        foreach ($dates as $k => $date) {
            foreach ($date as $d) {
                $totals[$d] = 0;
            }
        }


        foreach ($periodLog as $logs) {
            foreach ($logs as $day => $hours) {
                foreach ($hours as $hour) {
                    if (array_key_exists($day, $totals)) {
                        $totals[$day] += $hour['timeSpentSeconds'];
                    } else {
                        $totals[$day] = 0;
                    }
                }
            }
        }
        ksort($totals);

        $output = '';
        foreach ($dates as $k => $date) {
            foreach ($date as $d) {
                if (array_key_exists($d, $totals)) {
                    $output .= '<td><ul class="list-group"><li class="list-group-item">' . $this->secToHR($totals[$d]) . '</li></ul></td>';
                } else {
                    $output .= '<td><ul class="list-group"><li class="list-group-item">0h 0m</li></ul></td>';
                }
            }
        }
        return $output;
    }

    /**
     * @param $seconds
     * @return string
     */
    private function secToHR($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds / 60) % 60);
        return "$hours h : $minutes m";
    }
}