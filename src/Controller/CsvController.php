<?php

namespace App\Controller;

use App\Csv\CsvImporter;
use App\Message\CsvUploaded;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class CsvController extends AbstractController
{
    #[Route('/', name: 'csv_async')]
    public function async(Request $request, MessageBusInterface $bus): Response
    {
        $form = $this->buildCsvForm();
        $sendNotification = $request->query->getBoolean('sendNotification');

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $importId = uuid_create();
            $content = (string) file_get_contents($form->get('csv')->getData()->getPathname());

            $bus->dispatch(new CsvUploaded($importId, $content, $sendNotification));

            $this->addFlash('success', 'The file will be imported ASAP.');

            return $this->redirectToRoute('csv_async', [
                'importId' => $importId,
                'sendNotification' => $sendNotification,
            ]);
        }

        return $this->render('csv/async.html.twig', [
            'form' => $form->createView(),
            'sendNotification' => $sendNotification,
        ]);
    }

    #[Route('/sync', name: 'csv_sync')]
    public function sync(Request $request, CsvImporter $csvImporter): Response
    {
        $form = $this->buildCsvForm();

        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $importId = uuid_create();
            $content = (string) file_get_contents($form->get('csv')->getData()->getPathname());

            $csvImporter->importCsv($content, $importId, false);

            $this->addFlash('success', 'The file has been imported.');

            return $this->redirectToRoute('csv_sync', [
                'importId' => $importId,
            ]);
        }

        return $this->render('csv/sync.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function buildCsvForm(): FormInterface
    {
        return $this->createFormBuilder()
            ->add('csv', FileType::class)
            ->add('send', SubmitType::class)
            ->getForm()
        ;
    }
}
