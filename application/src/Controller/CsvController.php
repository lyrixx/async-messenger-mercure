<?php

namespace App\Controller;

use App\Message\CsvUploaded;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class CsvController extends AbstractController
{
    private MessageBusInterface $bus;
    private string $tmpDir;

    public function __construct(MessageBusInterface $bus, string $tmpDir)
    {
        $this->bus = $bus;
        $this->tmpDir = $tmpDir;
    }

    /**
     * @Route("/", name="csv")
     */
    public function index(Request $request, PublisherInterface $publisher)
    {
        $form = $this->createFormBuilder()
            ->add('csv', FileType::class)
            ->add('send', SubmitType::class)
            ->getForm()
        ;

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $name = bin2hex(random_bytes(20));

            $file = $form->get('csv')->getData();
            $file->move($this->tmpDir, $name);

            $this->bus->dispatch(new CsvUploaded($name));

            $this->addFlash('success', 'The file will be imported ASAP.');

            return $this->redirectToRoute('csv');
        }

        return $this->render('csv/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
