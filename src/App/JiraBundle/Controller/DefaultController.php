<?php

namespace App\JiraBundle\Controller;

use App\JiraBundle\Form\FilterForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\User;

/**
 * Class DefaultController
 * @package App\JiraBundle\Controller
 */
class DefaultController extends Controller
{

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/", name="login")
     */
    public function index(Request $request)
    {
        if ($request->getSession()->get('user')) {
            return $this->redirectToRoute('time_report');
        }
        if ($request->isMethod('POST')) {
            $success = $this->get('jira.api')->authenticate($request->get('username'), $request->get('password'));

            if ($success) {
                $request->getSession()->set('user', new User($request->get('username'), $request->get('password')));

                return $this->redirect($this->generateUrl('time_report', [], UrlGeneratorInterface::ABSOLUTE_URL));
            }
            return $this->render('default/login.html.twig', [
                'error' => 'Invalid username or password'
            ]);
        }
        return $this->render('default/login.html.twig');
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/logout", name="logout")
     */
    public function logout(Request $request)
    {
        $request->getSession()->set('user', null);

        return $this->redirectToRoute('login');
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/secure/time-report", name="time_report")
     */
    public function timeReport(Request $request)
    {
        if (!$request->getSession()->get('user')) {
            return $this->redirectToRoute('login');
        }
        $project = $this->getParameter('jira_project');
        $start = new \DateTime();
        $start = $start->modify('-8 days');
        $end = new \DateTime();

        $data = [
            'start' => $start,
            'end' => $end,
            'project' => $project,
            'user' => $assignee = $request->getSession()->get('user')->getUsername(),
        ];

        $fromDate = $data['start']->format('Y-m-d');
        $toDate = $data['end']->format('Y-m-d');

        $form = $this->createForm(FilterForm::class, $data, ['csrf_protection' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $fromDate = $form->get('start')->getData()->format('Y-m-d');
            $toDate = $form->get('end')->getData()->format('Y-m-d');
            $project = $form->get('project')->getData();
        }
        /** @var array $dates */
        $startDate = new \DateTime($fromDate);
        $endDate = new \DateTime($toDate);

        $diff = $startDate->diff($endDate);

        $dates[$startDate->format('Y-m-d')] = $startDate->format('N') >= 6;
        for ($i = 1; $i <= $diff->d; $i++) {
            $startDate = new \DateTime($fromDate);
            $newDate = $startDate->modify('+' . $i . ' days');
            $dates[$newDate->format('Y-m-d')] = $newDate->format('N') >= 6;
        }
        $periodLog = $this->get('jira.api')->workLog($form->get('user')->getData(), $project, $toDate, $fromDate);

        return $this->render('default/time-report.html.twig', [
            'periodLog' => $periodLog,
            'form' => $form->createView(),
            'dates' => $dates
        ]);
    }
}
