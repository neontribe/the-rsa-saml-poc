<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnvironmentType extends AbstractType
{

    private array $data;

    public function __construct()
    {
        $this->data = json_decode(file_get_contents(__DIR__ . "/../../Resources/config/environments.json"), true);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = [];
        foreach ($this->data as $client => $applications) {
            $apps = [];
            foreach ($applications as $application => $secrets) {
                $apps[$application] = $application;
            }
            $data[$client] = $apps;
        }
        $builder
            ->add('client', ChoiceType::class, [ "choices" => $data])
            ->add('save', SubmitType::class, ['label' => 'Do it'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
