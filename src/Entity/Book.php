<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Since;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;

#[Hateoas\Relation(
    name: 'self',
    href: new Hateoas\Route(
        name: 'detailBook',
        parameters: ['id' => 'expr(object.getId())']
    ),
    exclusion: new Hateoas\Exclusion(groups: ['getBooks'])
)]
#[Hateoas\Relation(
    name: 'delete',
    href: new Hateoas\Route(
        name: 'deleteBook',
        parameters: ['id' => 'expr(object.getId())']
    ),
    exclusion: new Hateoas\Exclusion(groups: ['getBooks'], excludeIf: 'expr(not is_granted("ROLE_ADMIN"))')
)]
#[Hateoas\Relation(
    name: 'update',
    href: new Hateoas\Route(
        name: 'updateBook',
        parameters: ['id' => 'expr(object.getId())']
    ),
    exclusion: new Hateoas\Exclusion(groups: ['getBooks'], excludeIf: 'expr(not is_granted("ROLE_ADMIN"))')
)]
#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ApiResource()]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["getBooks", "getAuthors"])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["getBooks", "getAuthors"])]
    #[Assert\NotBlank(message: "Le titre du livre est obligatoire")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le titre doit faire au moins {{ limit }} caractères", maxMessage: "Le titre ne peut pas faire plus de {{ limit }} caractères")]
    private $title;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["getBooks", "getAuthors"])]
    private $coverText;

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'books')]
    #[ORM\JoinColumn(onDelete:"CASCADE")]
    #[Groups(["getBooks"])]
    private $author;

    #[Groups(["getBooks"])]
    #[Since("2.0")]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCoverText(): ?string
    {
        return $this->coverText;
    }

    public function setCoverText(?string $coverText): self
    {
        $this->coverText = $coverText;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }
}