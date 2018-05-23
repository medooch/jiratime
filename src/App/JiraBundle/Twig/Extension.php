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
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('sec_to_hours', [$this, 'secToHR']),

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

        /**
         * prepare total array for all days
         * @var string $date
         * @var  bool $isWeekend
         */
        foreach ($dates as $date => $isWeekend) {
            $totals[$date] = 0;
        }

        /**
         * calculate totals per day
         * @var array $logs
         */
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

        /**
         * HTML output
         * @var string $output
         */
        $output = '';
        foreach ($dates as $date => $isWeekend) {
            $output .= array_key_exists($date, $totals) ? $this->secToHR($totals[$date]) : $this->secToHR(0);
        }
        return $output;
    }

    /**
     * @param $seconds
     * @return string
     */
    public function secToHR($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds / 60) % 60);
        return '<td><ul class="list-group"><li class="list-group-item">' . "$hours h : $minutes m" . '</li></ul></td>';
    }
}