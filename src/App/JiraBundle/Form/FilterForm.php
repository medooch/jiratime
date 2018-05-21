<?php
/**
 * This file is part of the jiratime.
 * Created by trimechmehdi.
 * Date: 5/21/18
 * Time: 13:12
 * @author: Trimech Mehdi <trimechmehdi11@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\JiraBundle\Form;
use App\JiraBundle\Services\JiraService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class FilterForm
 * @package App\JiraBundle\Form
 */
class FilterForm extends AbstractType
{
    /** @var JiraService $jiraService */
    private $jiraService;
    /**
     * FilterForm constructor.
     * @param JiraService $jiraService
     */
    public function __construct(JiraService $jiraService)
    {
        $this->jiraService = $jiraService;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('end', DateType::class, [
                'widget' => 'single_text',
            ]);

        $choices = [];
        if ($projects = $this->jiraService->projects()) {
            foreach ($projects as $project) {
                $choices[$project['name']] = $project['key'];
            }
        }
        $builder->add('project', ChoiceType::class, [
            'choices' => $choices,
            'attr' => [
                'data-live-search' => true
            ]
        ]);

        $builder->add('user', TextType::class, [
            'attr' => [
                'placeholder' => 'username'
            ],
            'required' => false
        ]);

        $builder->setMethod('GET');
    }
}