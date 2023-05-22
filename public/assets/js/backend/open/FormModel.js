export const c = {
    // 局部注册名为 'my-component' 的子组件
    'button-counter': {
        name: 'button-counter', // 组件名
        data: function () {
            return {
                count: 0
            }
        },
        template: '<button v-on:click="count++">You clicked me {{ count }} times.</button>'
    }
}