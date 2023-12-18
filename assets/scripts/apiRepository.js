import Vue from "vue";

let ajax_url = document.head.querySelector('meta[name="home-url"]').content+'/api'

export default {

    getData(){

        return new Promise((resolve, reject) => {

            Vue.http.get(window.blog.home_url + '/app/uploads/data.json').then(response => {

                resolve(response.body);

            },reject)
        })
    },
}