<?php

namespace App\Controller;

use App\Entity\Article;
use App\Message\Article as ArticleMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/article')]
final class ArticleController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $bus,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route(name: 'app_article_index')]
    public function index(): Response
    {
        $article = new Article();
        $article->setTitle('My first article');

        $this->em->persist($article);

        $this->bus->dispatch(new ArticleMessage($article->getId() ?? 0));

        return $this->render('article/index.html.twig', [
            'controller_name' => 'ArticleController',
        ]);
    }
}
