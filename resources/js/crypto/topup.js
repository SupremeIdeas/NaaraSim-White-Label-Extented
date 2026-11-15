// Crypto top-up: connect a wallet (Reown AppKit modal: WalletConnect, Trust Wallet, MetaMask, Coinbase and more),
// prove it is theirs with one free signature, then send the exact USDC/USDT transfer to our address with one wallet confirmation.
// No private key ever reaches this page or our server: the wallet signs, we only read the chain. Without a Reown project id it falls
// back to the wallet injected in the browser (the same code path, so wallet apps' in-app browsers work too).
import { BrowserProvider, Contract, parseUnits, formatUnits } from 'ethers';

const ERC20 = ['function transfer(address to, uint256 value) returns (bool)', 'function balanceOf(address owner) view returns (uint256)'];
let modal = null;
let cfg = null;
let hooks = {};

let injected = null;   // the specific browser wallet the member picked

/** Which wallets are built into this browser (their app's in-app browser, or an extension). Several can be present at once (EIP-5749 `providers`). */
function installed() {
    const list = window.ethereum ? (Array.isArray(window.ethereum.providers) && window.ethereum.providers.length ? window.ethereum.providers : [window.ethereum]) : [];
    const find = (fn) => list.find(fn) || null;
    return {
        metamask: find((p) => p.isMetaMask && !p.isTrust && !p.isTrustWallet && !p.isCoinbaseWallet && !p.isRabby),
        trust: find((p) => p.isTrust || p.isTrustWallet),
        coinbase: find((p) => p.isCoinbaseWallet || p.isCoinbaseBrowser),
        any: list[0] || null,
    };
}

function walletProvider() {
    if (injected) return injected;
    if (modal && modal.getWalletProvider && modal.getWalletProvider()) return modal.getWalletProvider();
    return window.ethereum || null;
}

async function signer() {
    const p = walletProvider();
    if (!p) throw new Error('Connect a wallet first.');
    return new BrowserProvider(p).getSigner();
}

async function init(config, h) {
    cfg = config;
    hooks = h || {};
    if (!cfg.projectId) {
        // No Reown project yet: injected wallet only.
        if (window.ethereum?.on) {
            window.ethereum.on('accountsChanged', (a) => hooks.account?.(a[0] || null));
            window.ethereum.on('chainChanged', (c) => hooks.chain?.(parseInt(c, 16)));
        }
        return { mode: 'injected' };
    }
    const [{ createAppKit }, { EthersAdapter }, nets] = await Promise.all([import('@reown/appkit'), import('@reown/appkit-adapter-ethers'), import('@reown/appkit/networks')]);
    const networks = cfg.chains.map((id) => ({ 1: nets.mainnet, 137: nets.polygon }[id])).filter(Boolean);
    modal = createAppKit({
        adapters: [new EthersAdapter()],
        networks,
        projectId: cfg.projectId,
        metadata: { name: cfg.appName, description: 'Top up your wallet with crypto', url: location.origin, icons: [location.origin + '/favicon.ico'] },
        features: { analytics: false, email: false, socials: false, onramp: false, swaps: false, history: false },
        themeMode: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    });
    modal.subscribeAccount((a) => hooks.account?.(a?.isConnected ? a.address : null));
    modal.subscribeNetwork?.((n) => hooks.chain?.(n?.chainId ? Number(n.chainId) : null));
    return { mode: 'walletconnect' };
}

/** Connect the browser wallet the member picked (`kind`: metamask | trust | coinbase | any), or open Reown's WalletConnect picker (`walletconnect`). */
async function connect(kind) {
    if (kind === 'walletconnect') {
        if (!modal) throw new Error('WalletConnect is not switched on yet. Pick a wallet above, or ask the site admin.');
        injected = null;
        await modal.open({ view: 'Connect' });
        return;
    }
    const p = installed()[kind || 'any'] || installed().any;
    if (!p) throw new Error('That wallet is not available in this browser.');
    injected = p;
    const a = await p.request({ method: 'eth_requestAccounts' });
    hooks.account?.(a[0] || null);
    hooks.chain?.(parseInt(await p.request({ method: 'eth_chainId' }), 16));
}

async function disconnect() {
    injected = null;
    if (modal?.disconnect) await modal.disconnect();
    hooks.account?.(null);
}

/** personal_sign (EIP-191) over the server's challenge text. */
async function sign(message) {
    return (await signer()).signMessage(message);
}

async function ensureChain(chainId) {
    const p = walletProvider();
    const now = parseInt(await p.request({ method: 'eth_chainId' }), 16);
    if (now === chainId) return;
    if (modal?.switchNetwork) {
        const nets = await import('@reown/appkit/networks');
        await modal.switchNetwork({ 1: nets.mainnet, 137: nets.polygon }[chainId]);
    } else {
        const hex = '0x' + chainId.toString(16);
        try {
            await p.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: hex }] });
        } catch (e) {
            // 4902: the wallet has never heard of this network (common for Polygon in a fresh wallet): add it, then switch.
            const add = { 137: { chainId: hex, chainName: 'Polygon Mainnet', nativeCurrency: { name: 'POL', symbol: 'POL', decimals: 18 }, rpcUrls: ['https://polygon-bor-rpc.publicnode.com'], blockExplorerUrls: ['https://polygonscan.com'] } }[chainId];
            if ((e && (e.code === 4902 || e.data?.originalError?.code === 4902)) && add) {
                await p.request({ method: 'wallet_addEthereumChain', params: [add] });
            } else {
                throw e;
            }
        }
    }
}

/** One ERC-20 transfer of exactly `amount` (a decimal string) to our address. Returns the transaction hash. The wallet shows its own confirmation. */
async function pay({ chainId, contract, decimals, to, amount }) {
    await ensureChain(chainId);
    const s = await signer();
    const units = parseUnits(String(amount), decimals);
    const token = new Contract(contract, ERC20, s);
    const have = await token.balanceOf(await s.getAddress());
    if (have < units) throw Object.assign(new Error('Your wallet balance is lower than this amount (' + formatUnits(have, decimals) + ' available).'), { friendly: true });
    const tx = await token.transfer(to, units);
    return tx.hash;
}

window.CryptoTopupJs = { init, connect, disconnect, sign, pay, installed: () => { const i = installed(); return { metamask: !!i.metamask, trust: !!i.trust, coinbase: !!i.coinbase, any: !!i.any }; } };
document.dispatchEvent(new CustomEvent('crypto-topup-ready'));
