import{d as p,r as f,e as h,i as g,N as d,f as m}from"./ConstantsUtil-wA8GK4UF.js";import{n as i,r as b,c as w}from"./index-CraI3ZTD.js";import"./index-B2EZ3qoP.js";const k=p`
  :host {
    width: 100%;
  }

  button {
    padding: ${({spacing:e})=>e[3]};
    display: flex;
    gap: ${({spacing:e})=>e[3]};
    justify-content: space-between;
    width: 100%;
    border-radius: ${({borderRadius:e})=>e[4]};
    background-color: transparent;
  }

  @media (hover: hover) {
    button:hover:enabled {
      background-color: ${({tokens:e})=>e.theme.foregroundSecondary};
    }
  }

  button:focus-visible:enabled {
    background-color: ${({tokens:e})=>e.theme.foregroundSecondary};
    box-shadow: 0 0 0 4px ${({tokens:e})=>e.core.foregroundAccent040};
  }

  button[data-clickable='false'] {
    pointer-events: none;
    background-color: transparent;
  }

  wui-image,
  wui-icon {
    width: ${({spacing:e})=>e[10]};
    height: ${({spacing:e})=>e[10]};
  }

  wui-image {
    border-radius: ${({borderRadius:e})=>e[16]};
  }

  .token-name-container {
    flex: 1;
  }
`;var n=function(e,o,a,l){var u=arguments.length,r=u<3?o:l===null?l=Object.getOwnPropertyDescriptor(o,a):l,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")r=Reflect.decorate(e,o,a,l);else for(var c=e.length-1;c>=0;c--)(s=e[c])&&(r=(u<3?s(r):u>3?s(o,a,r):s(o,a))||r);return u>3&&r&&Object.defineProperty(o,a,r),r};let t=class extends g{constructor(){super(...arguments),this.tokenName="",this.tokenImageUrl="",this.tokenValue=0,this.tokenAmount="0.0",this.tokenCurrency="",this.clickable=!1,this.imageError=!1}render(){return m`
      <button data-clickable=${String(this.clickable)}>
        <wui-flex gap="2" alignItems="center">
          ${this.visualTemplate()}
          <wui-flex
            flexDirection="column"
            justifyContent="space-between"
            gap="1"
            class="token-name-container"
          >
            <wui-text variant="md-regular" color="primary" lineClamp="1">
              ${this.tokenName}
            </wui-text>
            <wui-text variant="sm-regular-mono" color="secondary">
              ${d.formatNumberToLocalString(this.tokenAmount,4)} ${this.tokenCurrency}
            </wui-text>
          </wui-flex>
        </wui-flex>
        <wui-flex
          flexDirection="column"
          justifyContent="space-between"
          gap="1"
          alignItems="flex-end"
          width="auto"
        >
          <wui-text variant="md-regular-mono" color="primary"
            >$${this.tokenValue.toFixed(2)}</wui-text
          >
          <wui-text variant="sm-regular-mono" color="secondary">
            ${d.formatNumberToLocalString(this.tokenAmount,4)}
          </wui-text>
        </wui-flex>
      </button>
    `}updated(o){o.has("tokenImageUrl")&&(this.imageError=!1)}visualTemplate(){return this.tokenName&&this.tokenImageUrl&&!this.imageError?m`<wui-image
        alt=${this.tokenName}
        src=${this.tokenImageUrl}
        @onLoadError=${this.handleImageError}
      ></wui-image>`:m`<wui-icon name="coinPlaceholder" color="default"></wui-icon>`}handleImageError(){this.imageError=!0}};t.styles=[f,h,k];n([i()],t.prototype,"tokenName",void 0);n([i()],t.prototype,"tokenImageUrl",void 0);n([i({type:Number})],t.prototype,"tokenValue",void 0);n([i()],t.prototype,"tokenAmount",void 0);n([i()],t.prototype,"tokenCurrency",void 0);n([i({type:Boolean})],t.prototype,"clickable",void 0);n([b()],t.prototype,"imageError",void 0);t=n([w("wui-list-token")],t);
