class InvoiceController
{
  public function actionCreate($client_id, array $item_ids)
    {
      $client = Client::find()->byId($client_id)->one();
      if (!$client) { throw new NotFoundException('No client'); }

      $items = Item::find()->byId($item_ids)->all();
      if (!$items) { throw new NotFoundException('No items'); }

      $invoice = new Invoice();
      $invoice->client = $client;
      $invoice->status = 'new';
      $invoice->items = $items;
      $invoice->save();

      $this->notifyInvoiceCreated($client->email, $invoice);

      return $this->render('invoice', compact('invoice')]);
    }
}

class InvoiceController
{
    public function actionAddItem($invoice_id, $item_id = null)
    {
        $invoice = Invoice::find()->byId($invoice_id)->one();
        if (!$invoice) { throw new NotFoundException('No invoice'); }

        $item = Item::find()->byId($item_id)->one();
        if (!$item) { throw new NotFoundException('No item'); }

        if ($invoice->status !== 'new') { throw new HttpException(403); }

        $invoice->items[] = $item;
        $invoice->save();

        return $this->render('invoice', ['invoice' => $invoice]);
    }
}

// -------------------------------------------------------

class Client
{
    private $id;
    private $name;
    private $address;
    private $phone;

    public function __construct($id, $name, $address, $phone)
    {
        if (!preg_match('/^\d+$/', $phone)) {
            throw new ValidationException($phone);
        }
        $this->phone = $phone;

        // ... name, address
    }
}

new Client(
    UUID::create(),
    'Фамилия Имя Отчество',
    'ул. Кодеров, д. 0xFF',
    '380441234567'
);

class Address
{
    private $country;
    private $city;
    private $zip;
    private $lines;

    public function __construct($country, $city, $zip, $lines)
    {
        $this->zip = $this->validateZip($zip);
        // TODO: city, zip, lines
    }

    private function validateZip($zip)
    {
        if (!preg_match('/^\d+$/', $zip) {
            throw new ZipValidationException($zip);
        }

        return $zip;
    }
}

new Client(
    UUID::create(),
    new Name('Фамилия', 'Имя', 'Отчество'),
    new Address('Украина', 'Киев', '01001', ['ул. Кодеров, д. 0xFF']),
    new Phone('380', '44', '1234567')
);

class Item
{
    private $id;
    private $name;
    private $description;
    private $price;

    public function __construct($id, $name, Money $price) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
    }

    // TODO: Getters and setters
}

$item = new Item(UUID::create(), 'Silver bullet', Money::USD(1000));
$item->setDescription('This bullet is a killing feature!');

class LineItem
{
    protected $item;
    protected $quantity;

    public function __construct(Item $item, $quantity = 1) {
        $this->item = $item;
        $this->quantity = $quantity;
    }

    // TODO: Getters (no setters in VO)
}


class Invoice
{
    protected $id;
    protected $client;
    protected $lineItems = [];
    protected $status;

    public function __construct($id, Client $client)
    {
        $this->id = $id;
        $this->client = $client;
    }

    public function getId() {}
    public function getClient() {}

    public function getLineItems() {}
    public function setLineItems() {}

    public function getStatus() {}
    public function setStatus($status) {}
}     

$item = new Item(UUID::create(), 'Silver bullet', Money::USD(1000));
$quantity = 2;

$invoice = new Invoice(UUID::create(), $client);
$invoice->setStatus('new');
$invoice->setLineItems([new LineItem($item, $quantity)]);

$anotherLine = new LineItem(
    new Item(UUID::create(), 'Rage cup', Money::USD(500))
);

$invoice->setLineItems([$anotherLine]);

$invoice->setLineItems([$anotherLineItem]);
$invoice->setStatus('processing');

// VS

$invoice->lineItems = [$anotherLineItem];
$invoice->status = 'processing';


abstract class Status
{
    /**
     * @property array Class names of next possible statuses
     */
    protected $next = [];

    public function canBeChangedTo(self $status): bool
    {
        $className = get_class($status);

        return in_array($className, $this->next, true);
    }

    public function allowsModification(): bool
    {
        return true;
    }
}

class NewStatus extends Status
{
    protected $next = [ProcessingStatus::class, RejectedStatus::class];
}

class Invoice
{
    public function changeStatus(Status $status)
    {
        if (!$this->status->canBeChangedTo($status)) {
            throw new WrongStatusChangeDirectionException();
        }

        $this->status = $status;
    }
}

class Invoice
{
    public function addLineItem(LineItem $line)
    {
        if (!$this->status->allowsModification()) {
            throw new ModificationProhibitedException();
        }

        $this->line->push($line);
    }

    public function removeLineItem(LineItem $line)
    {
        // Copy-paste status check?
    }
}

abstract class Status
{
    public function ensureCanBeChangedTo(self $status): void
    {
        if (!$this->canBeChangedTo($status)) {
            throw new WrongStatusChangeDirectionException();
        }
    }

    public function ensureAllowsModification(): void
    {
        if (!$this->allowsModification()) {
            throw new ModificationProhibitedException();
        }
    }
}

class Invoice
{
    public function changeStatus(Status $status)
    {
        $this->status->ensureCanBeChangedTo($status);
        $this->status = $status;
    }

    public function addLineItem(LineItem $lineItem)
    {
        $this->status->ensureAllowsModification();
        $this->lineItmes->push($lineItem);
    }

    public function removeLineItem(LineItem $lineItem)
    {
        $this->status->ensureAllowsModification();
        // ...
    }
}

$invoice = new Invoice(UUID::create(), $client);

$invoice->addLineItem(new LineItem($item, $quantity));
$invoice->changeStatus(new NewStatus());

// save...?


interface InvoiceRepositoryInterface
{
    /** @throws InvoiceNotFoundException when no invoice exists */
    public function findById($id);
    public function add(Invoice $invoice);
    public function update(Invoice $invoice);
}

class SqlInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(Connection $db) {
        $this->db = $db;
    }

    public function create(Invoice $invoice)
    {
        $this->db->transactionBegin();

        $this->db->insert('invoice', [
            'id' => $invoice->getId(),
            'client_id' => $invoice->getClient()->getId(),
            'status' => $invoice->getStatus()
        ]);

        foreach ($invoice->getLineItems() as $lineItem) {
            $this->db->insert('invoice_lines', [
                'invoice_id' => $invoice->getId(),
                'item_id' => $lineItem->getItem()->getId(),
                'quantity' => $lineItem->getQuantity(),
            ]);
        }

        $this->db->transactionCommit();
    }
}

class InvoiceController
{
    public function actionCreate($client_id)
    {
        $client = $this->clientRepository->findById($client_id);

        $invoice = new Invoice(UUID::create(), $client);
        $invoice->changeStatus(new NewStatus());
        $this->invoiceRepository->add($invoice);

        return $invoice->getId();
    }
}

// -------------------------------------------
class InvoiceServiceInterface
{
    public function create($clientId);
    public function addLineItem($id, $itemId, $quantity);
    public function reject($id);
    public function process($id);
}

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        ClientRepositoryInterface $clientRepository,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->clientRepository = $clientRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    public function create($clientId)
    {
        $client = $this->clientRepository->findById($clientId);

        $invoice = new Invoice(UUID::create(), $client);
        $invoice->changeStatus(new NewStatus());

        $this->invoiceRepository->create($invoice);

        return $invoice->getId();
    }
}

class InvoiceService
{
    public function reject($invoiceId)
    {
        $invoice = $this->invoiceRepository->findById($id);
        if ($invoice === null) {
            return null;
        }

        $invoice->changeStatus(new RejectedStatus());

        if ($this->invoiceRepository->update($invoice) === false) {
            return null;
        }

        return true;
    }
}

class InvoiceController
{
    public function actionCreate($client_id)
    {
        $invoiceId = $this->invoiceService->add($client_id);

        return $invoiceId;
    }
}